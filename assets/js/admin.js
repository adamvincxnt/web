$(document).ready(function () {
    // DataTable initialization
    if ($('#dataTable').length) {
        $('#dataTable').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/th.json'
            },
            responsive: true,
            pageLength: 25,
            order: [[0, 'desc']]
        });
    }

    // Image preview
    $('.image-input').on('change', function () {
        const file = this.files[0];
        const preview = $(this).siblings('.image-preview');

        if (file) {
            const reader = new FileReader();
            reader.onload = function (e) {
                preview.html(`<img src="${e.target.result}" class="img-thumbnail" style="max-width: 200px;">`);
            };
            reader.readAsDataURL(file);
        }
    });

    // Status update
    $('.status-select').on('change', function () {
        const orderId = $(this).data('order-id');
        const status = $(this).val();

        updateOrderStatus(orderId, status);
    });

    // Delete confirmation
    $('.btn-delete').on('click', function (e) {
        e.preventDefault();

        if (confirm('คุณแน่ใจหรือไม่ที่จะลบรายการนี้?')) {
            const form = $(this).closest('form');
            form.submit();
        }
    });
});

function updateOrderStatus(orderId, status) {
    $.ajax({
        url: '/admin/update-order-status',
        method: 'POST',
        data: {
            order_id: orderId,
            status: status
        },
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                showAlert('อัปเดตสถานะเรียบร้อย', 'success');

                // Update status badge
                const $row = $(`[data-order-id="${orderId}"]`).closest('tr');
                const statusBadge = getStatusBadge(status);
                $row.find('.status-badge').html(statusBadge);
            } else {
                showAlert(response.message || 'เกิดข้อผิดพลาด', 'danger');
            }
        },
        error: function () {
            showAlert('เกิดข้อผิดพลาดในการเชื่อมต่อ', 'danger');
        }
    });
}

function getStatusBadge(status) {
    const statusMap = {
        'pending': '<span class="badge bg-warning">รอชำระเงิน</span>',
        'paid': '<span class="badge bg-info">ชำระเงินแล้ว</span>',
        'confirmed': '<span class="badge bg-primary">ยืนยันแล้ว</span>',
        'preparing': '<span class="badge bg-secondary">กำลังเตรียม</span>',
        'ready': '<span class="badge bg-success">พร้อมส่ง</span>',
        'delivered': '<span class="badge bg-success">ส่งแล้ว</span>',
        'cancelled': '<span class="badge bg-danger">ยกเลิก</span>'
    };

    return statusMap[status] || '<span class="badge bg-light">ไม่ระบุ</span>';
}