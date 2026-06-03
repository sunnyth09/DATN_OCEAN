export function useFlyToCart() {
    /**
     * Thực hiện animation bay sản phẩm vào giỏ hàng
     * @param {HTMLElement} imageElement - Thẻ img của sản phẩm
     * @param {String} cartIconId - ID của icon giỏ hàng trên header
     * @returns {Promise<void>} - Trả về promise khi animation hoàn tất
     */
    const flyToCart = (imageElement, cartIconId = '#cart-icon') => {
        return new Promise((resolve) => {
            if (!imageElement) {
                console.warn('[useFlyToCart] Không tìm thấy phần tử ảnh sản phẩm.');
                resolve();
                return;
            }

            const cartElement = document.querySelector(cartIconId);
            if (!cartElement) {
                console.warn(`[useFlyToCart] Không tìm thấy phần tử giỏ hàng với id: ${cartIconId}`);
                resolve();
                return;
            }

            // 1. Lấy tọa độ của ảnh và giỏ hàng
            const imgRect = imageElement.getBoundingClientRect();
            const cartRect = cartElement.getBoundingClientRect();

            // 2. Clone ảnh
            const clone = imageElement.cloneNode(true);
            
            // Xóa id để tránh trùng lặp
            clone.removeAttribute('id');
            
            // 3. Set style ban đầu cho clone
            Object.assign(clone.style, {
                position: 'fixed',
                top: `${imgRect.top}px`,
                left: `${imgRect.left}px`,
                width: `${imgRect.width}px`,
                height: `${imgRect.height}px`,
                objectFit: 'contain',
                zIndex: '9999',
                pointerEvents: 'none', // Không cho click vào ảnh clone
                transition: 'all 0.8s cubic-bezier(0.25, 1, 0.5, 1)'
            });

            // Thêm vào body
            document.body.appendChild(clone);

            // 4. Bắt đầu animation sau một frame để CSS transition hoạt động
            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    // Tính toán vị trí đích
                    const targetTop = cartRect.top + cartRect.height / 2 - imgRect.height / 2;
                    const targetLeft = cartRect.left + cartRect.width / 2 - imgRect.width / 2;

                    // Scale nhỏ lại và opacity mờ dần, kết hợp dịch chuyển
                    Object.assign(clone.style, {
                        top: `${targetTop}px`,
                        left: `${targetLeft}px`,
                        transform: 'scale(0.1) rotate(15deg)',
                        opacity: '0.4'
                    });

                    // 5. Chờ transition kết thúc
                    setTimeout(() => {
                        clone.remove();
                        
                        // Thêm hiệu ứng rung/pop nhẹ cho giỏ hàng
                        cartElement.classList.add('cart-pop-animation');
                        setTimeout(() => {
                            cartElement.classList.remove('cart-pop-animation');
                        }, 300);

                        resolve();
                    }, 800); // 800ms tương ứng với 0.8s transition
                });
            });
        });
    };

    return {
        flyToCart
    };
}
