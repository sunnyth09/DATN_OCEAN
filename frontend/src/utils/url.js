export const trimTrailingSlash = (value = '') => String(value).replace(/\/+$/, '');

const sanitizeProtocol = (url = '') => {
  if (!url || typeof window === 'undefined') return url;
  if (window.location.protocol === 'https:' && /^http:\/\//i.test(url)) {
    return url.replace(/^http:\/\//i, 'https://');
  }
  return url;
};

export const getApiBaseUrl = () => {
  const configuredUrl = import.meta.env.VITE_API_URL;

  if (configuredUrl) {
    return sanitizeProtocol(trimTrailingSlash(configuredUrl));
  }

  return `${window.location.protocol}//${window.location.hostname}:8383/api`;
};

export const getAppBaseUrl = () => {
  const configuredUrl = import.meta.env.VITE_BASE_URL || import.meta.env.VITE_APP_URL;

  let base = '';
  if (configuredUrl && /^https?:\/\//i.test(configuredUrl)) {
    base = trimTrailingSlash(configuredUrl.replace(/\/api\/?$/, ''));
  } else {
    base = getApiBaseUrl().replace(/\/api\/?$/, '');
  }

  base = base.replace(/\/storage(?:\/ui)?\/?$/i, '');
  return sanitizeProtocol(base);
};

export const getStorageUrl = (path = '') => {
  if (!path) return '';

  let strPath = String(path).trim();

  // Xóa bớt trùng lặp /storage/ui/storage/ hoặc /ui/storage/ hoặc /storage/storage/ nếu có trong path
  strPath = strPath
    .replace(/\/storage\/+(?:ui\/+)?storage\//gi, '/storage/')
    .replace(/\/ui\/+storage\//gi, '/storage/');

  if (/^https?:\/\//i.test(strPath)) {
    return sanitizeProtocol(strPath);
  }

  const normalizedPath = strPath
    .replace(/^\/+/, '')
    .replace(/^ui\/+storage\/+/, '')
    .replace(/^storage\/+ui\/+storage\/+/, '')
    .replace(/^storage\/+/, '');

  return sanitizeProtocol(`${getAppBaseUrl()}/storage/${normalizedPath}`);
};

export const getAbsoluteUrl = (path = '') => {
  if (!path) return '';
  if (/^(https?:|data:|blob:)/i.test(path)) {
    return sanitizeProtocol(path);
  }

  const normalizedPath = String(path).startsWith('/') ? path : `/${path}`;
  return sanitizeProtocol(`${getAppBaseUrl()}${normalizedPath}`);
};
