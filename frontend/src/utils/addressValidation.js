const PHONE_REGEX = /^(0|\+84)(3|5|7|8|9)[0-9]{8}$/;

export function sanitizeAddressPayload(address = {}) {
    return {
        ...address,
        recipient_name: String(address.recipient_name || '').trim(),
        phone: String(address.phone || '').trim(),
        address_line: String(address.address_line || '').trim(),
        ward: String(address.ward || '').trim(),
        district: String(address.district || '').trim(),
        province: String(address.province || '').trim(),
    };
}

export function validateAddressPayload(address = {}) {
    const data = sanitizeAddressPayload(address);
    const errors = {};

    if (!data.recipient_name) {
        errors.recipient_name = 'Vui lòng nhập Họ và tên';
    } else if (data.recipient_name.length < 2) {
        errors.recipient_name = 'Họ và tên phải có ít nhất 2 ký tự';
    } else if (data.recipient_name.length > 120) {
        errors.recipient_name = 'Họ và tên không được vượt quá 120 ký tự';
    }

    if (!data.phone) {
        errors.phone = 'Vui lòng nhập Số điện thoại';
    } else if (!PHONE_REGEX.test(data.phone)) {
        errors.phone = 'Số điện thoại không hợp lệ';
    }

    if (!data.province) {
        errors.province = 'Vui lòng chọn Tỉnh/Thành phố';
    }

    // Quận/Huyện KHÔNG validate — Ocean Express chỉ dùng Tỉnh + Phường/Xã.
    // district_code được tự động gán = province_code (district ảo) trong AddressSelector.

    if (!data.ward) {
        errors.ward = 'Vui lòng chọn Phường/Xã';
    }

    if (!data.address_line) {
        errors.address_line = 'Vui lòng nhập Địa chỉ cụ thể';
    } else if (data.address_line.length < 5) {
        errors.address_line = 'Địa chỉ cụ thể quá ngắn, vui lòng nhập số nhà/tên đường';
    } else if (data.address_line.length > 255) {
        errors.address_line = 'Địa chỉ cụ thể không được vượt quá 255 ký tự';
    }

    const firstError = Object.values(errors)[0] || '';

    return {
        valid: !firstError,
        firstError,
        errors,
        data,
    };
}
