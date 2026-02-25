import { describe, it, expect } from 'vitest'
import { extractAssetIdFromURL, isAssetContextByURL, normalizeCpRoot } from '../url-helpers'

describe('normalizeCpRoot', () => {
    it('keeps standard CP roots unchanged', () => {
        expect(normalizeCpRoot('/admin')).toBe('/admin')
    })

    it('removes trailing slashes', () => {
        expect(normalizeCpRoot('/admin/')).toBe('/admin')
    })

    it('adds a leading slash when missing', () => {
        expect(normalizeCpRoot('admin')).toBe('/admin')
    })

    it('normalizes root-level CP to empty prefix', () => {
        expect(normalizeCpRoot('/')).toBe('')
    })
})

describe('extractAssetIdFromURL', () => {
    it('extracts asset id with default /cp prefix', () => {
        const result = extractAssetIdFromURL('/cp/assets/browse/uploads/gallery/my-file.webp/edit', '/cp')
        expect(result).toBe('uploads::gallery/my-file.webp')
    })

    it('extracts asset id with custom /admin prefix', () => {
        // This is the exact scenario from issue #4
        const result = extractAssetIdFromURL('/admin/assets/browse/uploads/gallery/my-file.webp/edit', '/admin')
        expect(result).toBe('uploads::gallery/my-file.webp')
    })

    it('extracts asset id without /edit suffix', () => {
        const result = extractAssetIdFromURL('/cp/assets/browse/uploads/image.jpg', '/cp')
        expect(result).toBe('uploads::image.jpg')
    })

    it('handles nested paths', () => {
        const result = extractAssetIdFromURL('/cp/assets/browse/assets/blog/2026/hero.png/edit', '/cp')
        expect(result).toBe('assets::blog/2026/hero.png')
    })

    it('handles cp prefix with trailing slash', () => {
        const result = extractAssetIdFromURL('/admin/assets/browse/uploads/photo.jpg/edit', '/admin/')
        expect(result).toBe('uploads::photo.jpg')
    })

    it('returns null for non-asset URLs', () => {
        const result = extractAssetIdFromURL('/admin/collections/pages', '/admin')
        expect(result).toBeNull()
    })

    it('returns null when cp prefix does not match', () => {
        // Custom CP route is /admin but URL still has /cp — should NOT match
        const result = extractAssetIdFromURL('/cp/assets/browse/uploads/file.webp/edit', '/admin')
        expect(result).toBeNull()
    })
})

describe('isAssetContextByURL', () => {
    it('returns true for asset browse URLs', () => {
        expect(isAssetContextByURL('/admin/assets/browse/uploads/file.webp/edit', '/admin')).toBe(true)
    })

    it('returns false for non-asset URLs', () => {
        expect(isAssetContextByURL('/admin/collections/pages', '/admin')).toBe(false)
    })

    it('returns false for non-asset browse URLs inside CP', () => {
        expect(isAssetContextByURL('/admin/collections/browse/pages', '/admin')).toBe(false)
    })

    it('supports root-level CP for asset browsing', () => {
        expect(isAssetContextByURL('/assets/browse/uploads/file.webp/edit', '/')).toBe(true)
    })
})
