import { type Axios } from 'axios'

declare global {
    const Statamic: {
        $app: {
            config: {
                globalProperties: {
                    $axios: Axios
                }
            }
        }
        $toast: {
            success: (message: string) => void
            error: (message: string) => void
            info: (message: string) => void
        }
        $fieldActions: {
            add: (binding: string, action: FieldActionDefinition) => void
            get: (binding: string) => FieldActionDefinition[]
        }
        $config: {
            get: (key: string) => any
        }
        $progress: {
            loading: (name: string, state: boolean) => void
        }
        booting: (callback: () => void) => void
    }

    function __(key: string, params?: Record<string, string | number>): string
}

export interface FieldActionPayload {
    fieldPathPrefix?: string
    handle: string
    value: any
    config: Record<string, any>
    meta: Record<string, any>
    update: (newValue: any) => void
    updateMeta: (meta: any) => void
    isReadOnly: boolean
}

export interface FieldActionDefinition {
    title: string
    visible?: boolean | ((payload: FieldActionPayload) => boolean)
    visibleWhenReadOnly?: boolean
    icon?: string | ((payload: FieldActionPayload) => string)
    quick?: boolean | ((payload: FieldActionPayload) => boolean)
    dangerous?: boolean | ((payload: FieldActionPayload) => boolean)
    confirm?: boolean | {
        title?: string
        body?: string
        buttonText?: string
    }
    run: (payload: FieldActionPayload) => void | Promise<void>
}

export interface TriggerAltTextResponse {
    success: boolean
    message?: string
}

export interface CheckAltTextResponse {
    status: 'ready' | 'pending' | 'not_found' | 'error'
    caption?: string
    message?: string
}
