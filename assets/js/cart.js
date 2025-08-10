let cartUpdateInProgress = false;

$(document).ready(function () {
    // Add to cart button click
    $('.add-to-cart').on('click', function (e) {
        e.preventDefault();

        if (cartUpdateInProgress) return;

        const productId = $(this).data('product-id');
        const quantity = $(this).data('quantity') || 1;
        const $btn = $(this);

        addToCart(productId, quantity, $btn);
    });

    // Update cart quantity
    $(document).on('change', '.cart-quantity', function () {
        const productId = $(this).data('product-id');
        const quantity = parseInt($(this).val());

        if (quantity <= 0) {
            removeFromCart(productId);
        } else {
            updateCartQuantity(productId, quantity);
        }
    });

    // Remove from cart
    $(document).on('click', '.remove-from-cart', function (e) {
        e.preventDefault();
        const productId = $(this).data('product-id');
        removeFromCart(productId);
    });

    // Clear cart
    $(document).on('click', '.clear-cart', function (e) {
        e.preventDefault();

        if (confirm('คุณต้องการลบสินค้าทั้งหมดในตะกร้าหรือไม่?')) {
            clearCart();
        }
    });

    // Update cart display on page load
    updateCartDisplay();
});

function addToCart(productId, quantity = 1, $btn = null) {
    cartUpdateInProgress = true;

    if ($btn) {
        $btn.prop('disabled', true);
        $btn.html('<span class="spinner-border spinner-border-sm"></span>');
    }

    $.ajax({
        url: '/api/cart',
        method: 'POST',
        data: {
            action: 'add',
            product_id: productId,
            quantity: quantity
        },
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                updateCartDisplay();
                showAlert('เพิ่มสินค้าในตะกร้าแล้ว', 'success', 3000);

                // Animate cart badge
                $('.badge').addClass('animate');
                setTimeout(() => $('.badge').removeClass('animate'), 600);
            } else {
                showAlert(response.message || 'เกิดข้อผิดพลาด', 'danger');
            }
        },
        error: function () {
            showAlert('เกิดข้อผิดพลาดในการเชื่อมต่อ', 'danger');
        },
        complete: function () {
            cartUpdateInProgress = false;

            if ($btn) {
                $btn.prop('disabled', false);
                $btn.html('<i class="fas fa-cart-plus"></i>');
            }
        }
    });
}

function updateCartQuantity(productId, quantity) {
    $.ajax({
        url: '/api/cart',
        method: 'POST',
        data: {
            action: 'update',
            product_id: productId,
            quantity: quantity
        },
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                updateCartDisplay();
                updateCartTotal();
            } else {
                showAlert(response.message || 'เกิดข้อผิดพลาด', 'danger');
            }
        },
        error: function () {
            showAlert('เกิดข้อผิดพลาดในการเชื่อมต่อ', 'danger');
        }
    });
}

function removeFromCart(productId) {
    $.ajax({
        url: '/api/cart',
        method: 'POST',
        data: {
            action: 'remove',
            product_id: productId
        },
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                $(`[data-product-id="${productId}"]`).closest('.cart-item').fadeOut(function () {
                    $(this).remove();
                    updateCartDisplay();
                    updateCartTotal();
                });
                showAlert('ลบสินค้าออกจากตะกร้าแล้ว', 'info', 3000);
            } else {
                showAlert(response.message || 'เกิดข้อผิดพลาด', 'danger');
            }
        },
        error: function () {
            showAlert('เกิดข้อผิดพลาดในการเชื่อมต่อ', 'danger');
        }
    });
}

function clearCart() {
    $.ajax({
        url: '/api/cart',
        method: 'POST',
        data: {
            action: 'clear'
        },
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                $('.cart-items').empty();
                updateCartDisplay();
                showAlert('ลบสินค้าทั้งหมดแล้ว', 'info', 3000);
            }
        },
        error: function () {
            showAlert('เกิดข้อผิดพลาดในการเชื่อมต่อ', 'danger');
        }
    });
}

function updateCartDisplay() {
    $.ajax({
        url: '/api/cart',
        method: 'GET',
        data: { action: 'count' },
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                const cartCount = response.count;
                const cartBadge = $('.nav-link .badge');

                if (cartCount > 0) {
                    if (cartBadge.length) {
                        cartBadge.text(cartCount);
                    } else {
                        $('.nav-link .fa-shopping-cart').parent().append(
                            `<span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">${cartCount}</span>`
                        );
                    }
                } else {
                    cartBadge.remove();
                }
            }
        }
    });
}

function updateCartTotal() {
    $.ajax({
        url: '/api/cart',
        method: 'GET',
        data: { action: 'total' },
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                $('.cart-total').text(formatPrice(response.total));
            }
        }
    });
}