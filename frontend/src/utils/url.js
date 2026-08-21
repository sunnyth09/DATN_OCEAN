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

  if (configuredUrl && /^https?:\/\//i.test(configuredUrl)) {
    return sanitizeProtocol(trimTrailingSlash(configuredUrl.replace(/\/api$/, '')));
  }

  return sanitizeProtocol(getApiBaseUrl().replace(/\/api$/, ''));
};

export const getStorageUrl = (path = '') => {
  if (!path) return '';
  if (/^https?:\/\//i.test(path)) {
    return sanitizeProtocol(path);
  }

  const normalizedPath = String(path)
    .replace(/^\/+/, '')
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
