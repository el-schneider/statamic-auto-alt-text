/**
 * Normalize Statamic CP root into a predictable path prefix.
 *
 * Examples:
 * - "/admin/" -> "/admin"
 * - "admin" -> "/admin"
 * - "/" -> ""
 */
export function normalizeCpRoot(cpRoot: string = '/cp'): string {
    if (cpRoot === '' || cpRoot === '/') {
        return ''
    }

    const withLeadingSlash = cpRoot.startsWith('/') ? cpRoot : `/${cpRoot}`
    return withLeadingSlash.replace(/\/+$/, '')
}

/**
 * Extract asset container::path from a CP asset URL.
 *
 * @param pathname - The URL pathname (e.g. /admin/assets/browse/uploads/image.jpg/edit)
 * @param cpRoot - The CP route prefix path (e.g. /admin, /cp)
 */
export function extractAssetIdFromURL(pathname: string, cpRoot: string = '/cp'): string | null {
    const normalizedCpRoot = normalizeCpRoot(cpRoot)
    const escapedCpRoot = normalizedCpRoot.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
    const pathRegex = new RegExp(`^${escapedCpRoot}/assets/browse/([^/]+)/(.+?)(?:/edit)?/?$`)
    const match = pathname.match(pathRegex)

    if (match && match[1] && match[2]) {
        return `${match[1]}::${match[2]}`
    }

    return null
}

export function isAssetContextByURL(pathname: string, cpRoot: string = '/cp'): boolean {
    const normalizedCpRoot = normalizeCpRoot(cpRoot)
    return pathname.startsWith(`${normalizedCpRoot}/assets/browse/`)
}
