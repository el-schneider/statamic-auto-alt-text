/**
 * Extract asset container::path from a CP asset URL.
 */
export function extractAssetIdFromURL(pathname: string, cpRoot: string = '/cp'): string | null {
    const escapedCpRoot = cpRoot.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
    const pathRegex = new RegExp(`^${escapedCpRoot}/assets/browse/([^/]+)/(.+?)(?:/edit)?$`)
    const match = pathname.match(pathRegex)

    if (match && match[1] && match[2]) {
        return `${match[1]}::${match[2]}`
    }

    return null
}

export function isAssetContextByURL(pathname: string, cpRoot: string = '/cp'): boolean {
    return pathname.startsWith(`${cpRoot}/assets/browse/`)
}
