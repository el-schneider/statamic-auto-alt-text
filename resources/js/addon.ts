import './types'
import { type FieldActionPayload, type TriggerAltTextResponse, type CheckAltTextResponse } from './types'

const STATUS_READY = 'ready'
const STATUS_PENDING = 'pending'
const STATUS_NOT_FOUND = 'not_found'
const STATUS_ERROR = 'error'

const POLLING_INTERVAL_MS = 1000
const MAX_POLLING_ATTEMPTS = 15

function isAssetContextByURL(pathname: string): boolean {
    return pathname.includes('/assets/') || pathname.includes('/browse/')
}

function extractAssetIdFromURL(pathname: string): string | null {
    // Matches an URL like /cp/assets/browse/{container}/{path}/edit
    const pathRegex = /^\/cp\/assets\/browse\/([^/]+)\/(.+?)(?:\/edit)?$/
    const match = pathname.match(pathRegex)

    if (match && match[1] && match[2]) {
        return `${match[1]}::${match[2]}`
    }
    console.error('Could not determine Asset Container and Path from URL pattern:', pathname)
    return null
}

async function triggerAltTextGeneration(assetPath: string, fieldHandle: string): Promise<TriggerAltTextResponse> {
    try {
        const response = await Statamic.$app.config.globalProperties.$axios.post<TriggerAltTextResponse>(
            '/cp/auto-alt-text/generate',
            {
                asset_path: assetPath,
                field: fieldHandle,
            },
        )
        return response.data
    } catch (error: any) {
        console.error('Alt text trigger request error:', error)
        const errorMessage = error.response?.data?.message || error.message || 'Error communicating with server.'
        return { success: false, message: errorMessage }
    }
}

async function checkAltTextStatus(assetPath: string, fieldHandle: string): Promise<CheckAltTextResponse> {
    try {
        const response = await Statamic.$app.config.globalProperties.$axios.get<CheckAltTextResponse>(
            '/cp/auto-alt-text/check',
            {
                params: {
                    asset_path: assetPath,
                    field: fieldHandle,
                },
            },
        )
        return response.data
    } catch (error: any) {
        console.error('Alt text check request error:', error)
        const errorMessage = error.response?.data?.message || error.message || 'Error checking status.'
        return { status: STATUS_ERROR, message: errorMessage }
    }
}

async function pollForAltText(
    assetPath: string,
    handle: string,
    update: (newValue: any) => void,
): Promise<void> {
    let pollingAttempts = 0

    return new Promise((resolve, reject) => {
        const pollingIntervalId = window.setInterval(async () => {
            pollingAttempts++
            console.log(`Polling attempt ${pollingAttempts} for ${assetPath}...`)

            if (pollingAttempts > MAX_POLLING_ATTEMPTS) {
                clearInterval(pollingIntervalId)
                reject(new Error(__('auto-alt-text::messages.timeout')))
                return
            }

            const checkResponse = await checkAltTextStatus(assetPath, handle)

            switch (checkResponse.status) {
                case STATUS_READY:
                    clearInterval(pollingIntervalId)
                    console.log('Alt text ready:', checkResponse.caption)
                    Statamic.$toast.success(__('auto-alt-text::messages.success'))
                    update(checkResponse.caption)
                    resolve()
                    break
                case STATUS_PENDING:
                    break
                case STATUS_NOT_FOUND:
                    clearInterval(pollingIntervalId)
                    reject(new Error(checkResponse.message || __('auto-alt-text::messages.asset_not_found')))
                    break
                case STATUS_ERROR:
                default:
                    clearInterval(pollingIntervalId)
                    reject(new Error(checkResponse.message || __('auto-alt-text::messages.polling_error')))
                    break
            }
        }, POLLING_INTERVAL_MS)
    })
}

Statamic.booting(() => {
    console.log('Statamic Auto Alt Text Addon Initializing...')

    const addonConfig = Statamic.$config.get('autoAltText') || {}
    const enabledFields: string[] = addonConfig.enabledFields || ['alt', 'alt_text', 'alternative_text']
    const actionTitle: string = __('auto-alt-text::messages.generate_alt_text_action')

    Statamic.$fieldActions.add('text-fieldtype', {
        title: actionTitle,
        icon: 'image',
        visible: (payload: FieldActionPayload): boolean => {
            const currentPath = window.location.pathname;
            const isAssetContext = isAssetContextByURL(currentPath);
            return isAssetContext && enabledFields.includes(payload.handle);
        },
        run: async (payload: FieldActionPayload) => {
            const { handle, update } = payload;

            const currentPath = window.location.pathname;
            const assetId = extractAssetIdFromURL(currentPath);

            if (!assetId) {
                Statamic.$toast.error(__('auto-alt-text::messages.cannot_determine_asset_path'));
                return;
            }

            try {
                Statamic.$toast.info(__('auto-alt-text::messages.generation_started'));

                const triggerResponse = await triggerAltTextGeneration(assetId, handle);

                if (!triggerResponse.success) {
                    const errorMsg = triggerResponse.message || __('auto-alt-text::messages.trigger_failed');
                    Statamic.$toast.error(errorMsg);
                    return;
                }

                // Start polling - progress is handled automatically by Promise
                await pollForAltText(assetId, handle, update);

            } catch (error: any) {
                console.error('Error during alt text generation:', error);
                Statamic.$toast.error(error.message || __('auto-alt-text::messages.unexpected_error'));
            }
        }
    })

    console.log('Statamic Auto Alt Text Field Action Registered for text-fieldtype.')
})
