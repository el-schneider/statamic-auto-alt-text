/**
 * Extract asset container::path from a CP asset URL.
 *
 * @param pathname - The URL pathname (e.g. /admin/assets/browse/uploads/image.jpg/edit)
 * @param cpRoot - The CP route prefix path (e.g. /admin, /cp)
 */
export function extractAssetIdFromURL(pathname: string, cpUrl: string = '/cp'): string | null {
    const normalizedCpUrl = cpUrl.replace(/\/$/, '')
    const escapedCpUrl = normalizedCpUrl.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
    const pathRegex = new RegExp(`^${escapedCpUrl}/assets/browse/([^/]+)/(.+?)(?:/edit)?$`)
    const match = pathname.match(pathRegex)

    if (match && match[1] && match[2]) {
        return `${match[1]}::${match[2]}`
    }
    return null
}

export function isAssetContextByURL(pathname: string): boolean {
    return pathname.includes('/assets/') || pathname.includes('/browse/')
}
