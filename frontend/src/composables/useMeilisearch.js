import { ref, readonly } from 'vue';
import { instantMeiliSearch } from '@meilisearch/instant-meilisearch';
import { getAppBaseUrl, getStorageUrl } from '@/utils/url';

const MEILI_HOST = import.meta.env.VITE_MEILISEARCH_HOST || `${window.location.origin}/meilisearch`;
const MEILI_KEY  = import.meta.env.VITE_MEILISEARCH_KEY  || '';

const BASE_STORAGE = getAppBaseUrl();

/**
 * Tạo searchClient Meilisearch cho vue-instantsearch
 */
export function useMeilisearch() {
    const { searchClient } = instantMeiliSearch(MEILI_HOST, MEILI_KEY, {
        primaryKey: 'product_id',
        finitePagination: false,
    });

    return {
        searchClient,
        BASE_STORAGE,
    };
}

/**
 * Tạo URL ảnh sản phẩm từ path lưu trong Meilisearch
 */
export function getMeilisearchImageUrl(thumbnailUrl) {
    const defaultSvg = "data:image/svg+xml;charset=UTF-8," + encodeURIComponent(
        `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 500" width="100%" height="100%" opacity="0.6">
          <rect width="400" height="500" fill="#f4f9f9"/>
          <g transform="translate(130,230)">
            <path d="M150,50 C150,50 170,-20 100,-40 C30,-60 -20,20 -40,30 C-60,40 -80,20 -90,40 C-100,60 -70,90 -50,90 C-30,90 80,100 150,50 Z" fill="#1b8a9e"/>
          </g>
          <path d="M0,350 Q50,330 100,350 T200,350 T300,350 T400,350 L400,500 L0,500 Z" fill="#48b8c9" opacity="0.4"/>
        </svg>`
    );

    if (!thumbnailUrl || thumbnailUrl === '0' || thumbnailUrl === '') return defaultSvg;
    if (thumbnailUrl.startsWith('http')) return thumbnailUrl;
    return getStorageUrl(thumbnailUrl);
}
