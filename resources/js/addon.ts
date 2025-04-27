import { type Axios } from 'axios'

declare global {
    const Statamic: {
        $axios: Axios
        $toast: any
        $fieldActions: any
        $config: {
            get: (key: string) => any
        }
        booting: (callback: () => void) => void
    }

    // Declare Statamic's translation function
    function __(key: string, params?: Record<string, string | number>): string
}

// Status Constants
const STATUS_READY = 'ready'
const STATUS_PENDING = 'pending'
const STATUS_NOT_FOUND = 'not_found'
const STATUS_ERROR = 'error'

// Polling configuration
const POLLING_INTERVAL_MS = 1000 // Poll every 1 second
const MAX_POLLING_ATTEMPTS = 15 // Max attempts (e.g., 15 seconds)

// Define types for better clarity and maintainability
interface FieldActionContext {
    handle: string
    meta: Record<string, any> // Consider refining if meta structure is known
}

interface RunActionParams extends FieldActionContext {
    event: MouseEvent
    field: any // Keep as 'any' if structure is highly variable, otherwise define a type
    value: any
    meta: Record<string, any> & { asset?: any } // More specific meta type
    store: any // Vuex store instance
    storeName: string
    update: (newValue: any) => void
    vm?: any // Added vm parameter
}

interface TriggerAltTextResponse {
    success: boolean
    message?: string
}

interface CheckAltTextResponse {
    status: typeof STATUS_READY | typeof STATUS_PENDING | typeof STATUS_NOT_FOUND | typeof STATUS_ERROR
    caption?: string
    message?: string
}

// Helper function to determine if the context is related to assets based on URL
function isAssetContextByURL(pathname: string): boolean {
    return pathname.includes('/assets/') || pathname.includes('/browse/')
}

// Helper function to extract asset path (container::path) from URL
function extractAssetPathFromURL(pathname: string): string | null {
    // Matches an URL like /cp/assets/browse/{container}/{path}/edit
    const pathRegex = /^\/cp\/assets\/browse\/([^/]+)\/(.+?)(?:\/edit)?$/
    const match = pathname.match(pathRegex)

    if (match && match[1] && match[2]) {
        // Combine container (match[1]) and path (match[2]) with '::'
        return `${match[1]}::${match[2]}`
    }
    console.error('Could not determine Asset Container and Path from URL pattern:', pathname)
    return null
}

// Helper function to trigger the API call
async function triggerAltTextGeneration(assetPath: string, fieldHandle: string): Promise<TriggerAltTextResponse> {
    try {
        const response = await Statamic.$axios.post<TriggerAltTextResponse>('/cp/auto-alt-text/generate', {
            asset_path: assetPath,
            field: fieldHandle,
        })
        return response.data
    } catch (error: any) {
        console.error('Alt text trigger request error:', error)
        const errorMessage = error.response?.data?.message || error.message || 'Error communicating with server.'
        // Return a failure response
        return { success: false, message: errorMessage }
    }
}

// Helper function to poll the check endpoint
async function checkAltTextStatus(assetPath: string, fieldHandle: string): Promise<CheckAltTextResponse> {
    try {
        const response = await Statamic.$axios.get<CheckAltTextResponse>('/cp/auto-alt-text/check', {
            params: {
                asset_path: assetPath,
                field: fieldHandle,
            },
        })
        return response.data
    } catch (error: any) {
        console.error('Alt text check request error:', error)
        const errorMessage = error.response?.data?.message || error.message || 'Error checking status.'
        return { status: STATUS_ERROR, message: errorMessage }
    }
}

// UI State Management Helpers
function disableInteraction(vm: any): () => void {
    // TODO: Add a visual loading indicator class to the field wrapper (vm.$el?)
    const inputElement = vm?.$el?.querySelector('input, textarea') as HTMLInputElement | HTMLTextAreaElement | null

    if (!inputElement) {
        console.warn('Could not find input element to disable interaction.')
        return () => {} // Return a no-op cleanup if no element found
    }

    const originalReadOnlyState = inputElement.readOnly ?? false // Store original state
    inputElement.readOnly = true

    // Return the cleanup function
    return () => {
        if (inputElement) {
            inputElement.readOnly = originalReadOnlyState // Restore original state
        }
    }
}

// Polling Logic
async function pollForAltText(
    assetPath: string,
    handle: string,
    update: (newValue: any) => void,
    cleanupCallback: () => void,
): Promise<void> {
    let pollingAttempts = 0
    const pollingIntervalId = window.setInterval(async () => {
        pollingAttempts++
        console.log(`Polling attempt ${pollingAttempts} for ${assetPath}...`)

        if (pollingAttempts > MAX_POLLING_ATTEMPTS) {
            clearInterval(pollingIntervalId) // Stop this interval first
            cleanupCallback() // Then run the general cleanup
            console.error('Polling timeout exceeded for asset:', assetPath)
            Statamic.$toast.error(__('auto-alt-text::messages.timeout'))
            return
        }

        const checkResponse = await checkAltTextStatus(assetPath, handle)

        switch (checkResponse.status) {
            case STATUS_READY:
                clearInterval(pollingIntervalId)
                cleanupCallback()
                console.log('Alt text ready:', checkResponse.caption)
                Statamic.$toast.success(__('auto-alt-text::messages.success'))
                update(checkResponse.caption)
                break
            case STATUS_PENDING:
                // Continue polling
                break
            case STATUS_NOT_FOUND:
                clearInterval(pollingIntervalId)
                cleanupCallback()
                console.error('Asset not found during polling:', assetPath)
                Statamic.$toast.error(checkResponse.message || __('auto-alt-text::messages.asset_not_found'))
                break
            case STATUS_ERROR:
            default:
                clearInterval(pollingIntervalId)
                cleanupCallback()
                console.error('Error during polling:', checkResponse.message)
                Statamic.$toast.error(checkResponse.message || __('auto-alt-text::messages.polling_error'))
                break
        }
    }, POLLING_INTERVAL_MS)
}

Statamic.booting(() => {
    console.log('Statamic Auto Alt Text Addon Initializing...')

    const addonConfig = Statamic.$config.get('autoAltText') || {}
    const enabledFields: string[] = addonConfig.enabledFields || ['alt', 'alt_text', 'alternative_text']
    const actionTitle: string = __('auto-alt-text::messages.generate_alt_text_action')

    // Register the action for the text fieldtype
    Statamic.$fieldActions.add('text-fieldtype', {
        title: actionTitle,
        visible: ({ handle }: FieldActionContext): boolean => {
            const currentPath = window.location.pathname
            const isAssetContext = isAssetContextByURL(currentPath)

            // Show only for configured field handles within an asset context
            return isAssetContext && enabledFields.includes(handle)
        },

        // Action execution logic
        run: async ({ event, handle, meta, update, vm }: RunActionParams) => {
            const enableInteraction = disableInteraction(vm)

            try {
                const currentPath = window.location.pathname
                const assetPath = extractAssetPathFromURL(currentPath)

                if (!assetPath) {
                    Statamic.$toast.error(__('auto-alt-text::messages.cannot_determine_asset_path'))
                    enableInteraction()
                    return
                }

                console.log('Triggering alt text generation for:', assetPath, 'Field:', handle)

                // Show immediate feedback optimistically
                Statamic.$toast.info(__('auto-alt-text::messages.generation_started'))

                // Trigger the generation process
                const triggerResponse = await triggerAltTextGeneration(assetPath, handle)

                // Handle trigger failure
                if (!triggerResponse.success) {
                    const errorMsg = triggerResponse.message || __('auto-alt-text::messages.trigger_failed')
                    console.error('Trigger Error:', errorMsg)
                    Statamic.$toast.error(errorMsg)
                    enableInteraction()
                    return
                }

                // Start polling only on successful trigger
                await pollForAltText(assetPath, handle, update, enableInteraction)
            } catch (error: any) {
                // Catches errors primarily from extractAssetPathFromURL or unexpected issues
                console.error('Error during alt text generation action setup:', error)
                Statamic.$toast.error(error.message || __('auto-alt-text::messages.unexpected_error'))
                enableInteraction() // Ensure UI is re-enabled on setup error
            }
        },
    })

    console.log('Statamic Auto Alt Text Field Action Registered for text-fieldtype.')
})

// Add an empty export to treat this file as a module.
export {}
