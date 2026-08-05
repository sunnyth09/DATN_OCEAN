const fs = require('fs');

const files = [
    'src/features/profile/pages/ProfileOrderDetail.vue',
    'src/features/shop/pages/admin/AdminOrder.vue',
    'src/features/shop/pages/admin/AdminOrderDetail.vue',
    'src/features/shop/pages/client/GuestTracking.vue'
];

for (const file of files) {
    let content = fs.readFileSync(file, 'utf8');
    content = content.replace(/ghn_order_code/g, 'tracking_number');
    
    if (file.includes('AdminOrderDetail.vue')) {
        content = content.replace(/Mã GHN:/g, 'Mã vận đơn:');
        content = content.replace(/Đẩy qua GHN/g, 'Đẩy đơn vận chuyển');
        content = content.replace(/Đã đẩy đơn lên GHN thành công!/g, 'Đã đẩy đơn thành công!');
        content = content.replace(/Tra cứu GHN/g, 'Tra cứu đơn');
        content = content.replace(/Trạng thái GHN mới nhất/g, 'Trạng thái vận chuyển');
        content = content.replace(/GHN status/g, 'Trạng thái vận chuyển');
        content = content.replace(/Hủy vận đơn GHN/g, 'Hủy vận đơn');
        content = content.replace(/GHN thành công!/g, 'thành công!');
        content = content.replace(/vận đơn GHN/g, 'vận đơn');
        content = content.replace(/trạng thái GHN/g, 'trạng thái vận chuyển');
        content = content.replace(/đồng bộ GHN/g, 'đồng bộ vận chuyển');
    }
    
    fs.writeFileSync(file, content, 'utf8');
}
console.log('Fixed successfully');
