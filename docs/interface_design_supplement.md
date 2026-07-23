# PHẦN BỔ SUNG THIẾT KẾ GIAO DIỆN HỆ THỐNG ZONESPORT (ĐỒ ÁN TỐT NGHIỆP)

Tài liệu này bổ sung chi tiết các giao diện còn thiếu hoặc mới chỉ được liệt kê dưới dạng tiêu đề trong tài liệu gốc của bạn, đồng thời đề xuất thêm các phân hệ giao diện bắt buộc phải có đối với mô hình hệ thống kết hợp bán hàng thể thao (E-commerce) và đặt sân cầu lông (Badminton Booking System).

---

## PHẦN 1: MÔ TẢ CHI TIẾT CÁC GIAO DIỆN CÒN THIẾU TRONG DANH SÁCH KHÁCH HÀNG (CLIENT)

Dưới đây là thiết kế chi tiết cho các trang tĩnh, trang đăng ký và trang chính sách mà bạn đã liệt kê nhưng chưa có mô tả.

### 1.1 Giao diện đăng ký tài khoản (Client Signup)
*   **Mục đích:** Cho phép khách hàng mới đăng ký tài khoản thành viên để thực hiện mua sắm, đặt sân và tích lũy điểm thưởng.
*   **Các thành phần giao diện chính:**
    *   **Form Đăng ký:** Ô nhập Họ tên đầy đủ, Số điện thoại (dùng để xác thực SMS/OTP hoặc liên hệ nhận hàng), Email, Mật khẩu và Nhập lại mật khẩu.
    *   **Tích hợp điều khoản:** Checkbox đồng ý với "Điều khoản sử dụng" và "Chính sách bảo mật" của ZONESPORT.
    *   **Nút Đăng ký:** Kích hoạt quá trình kiểm tra định dạng và gửi dữ liệu lên Backend.
    *   **Đăng ký nhanh qua Mạng xã hội:** Nút đăng ký nhanh bằng tài khoản Google hoặc Facebook (đồng bộ và tự động tạo tài khoản dựa trên Email mạng xã hội).
*   **Luồng xử lý (Workflow):**
    1.  Người dùng điền đầy đủ thông tin vào Form. Hệ thống kiểm tra trực tiếp (Client-side validation): Số điện thoại đúng định dạng Việt Nam, độ dài mật khẩu tối thiểu 8 ký tự, mật khẩu khớp nhau.
    2.  Nhấp "Đăng ký" -> Hệ thống gọi API check xem Email/Sđt đã tồn tại trong DB chưa.
    3.  Nếu hợp lệ, hệ thống gửi email kích hoạt tài khoản (Verify Email Link) hoặc mã kích hoạt OTP qua Số điện thoại. Sau khi xác thực thành công, tài khoản chuyển từ trạng thái `inactive` sang `active` và tự động đăng nhập.

---

### 1.2 Các trang thông tin tĩnh & Hỗ trợ (Footer Pages / CMS)
Để website vận hành chuyên nghiệp và đáp ứng tiêu chuẩn SEO, các trang tĩnh này cần được hiển thị rõ ràng và có cấu trúc bài viết chuẩn mực.

#### A. Trang câu chuyện thương hiệu (Brand Story)
*   **Mục đích:** Giới thiệu lịch sử hình thành, sứ mệnh, tầm nhìn và giá trị cốt lõi của ZONESPORT nhằm xây dựng lòng tin với khách hàng.
*   **Chi tiết giao diện:**
    *   **Banner chính:** Hình ảnh chất lượng cao hoặc video ngắn về hành trình phát triển của ZONESPORT.
    *   **Nội dung bài viết:** Các mốc lịch sử phát triển quan trọng, triết lý kinh doanh ("Mang thể thao đến mọi nhà"), đội ngũ sáng lập.
    *   **Hình ảnh thực tế:** Kho ảnh về văn phòng, hệ thống cửa hàng, hình ảnh các giải đấu cầu lông do ZONESPORT tổ chức hoặc tài trợ.

#### B. Trang tuyển dụng (Careers)
*   **Mục đích:** Thu hút nhân sự cho các chi nhánh cửa hàng (lễ tân, nhân viên kho, thu ngân) và văn phòng điều hành.
*   **Chi tiết giao diện:**
    *   **Thông điệp tuyển dụng:** Môi trường làm việc năng động, chế độ đãi ngộ, quyền lợi.
    *   **Danh sách vị trí đang tuyển:** Lọc theo chi nhánh (Quận 1, Quận 7, Thủ Đức...) hoặc vị trí (Bán hàng, Kỹ thuật căng vợt, Lễ tân sân cầu lông...).
    *   **Chi tiết công việc (JD):** Nhiệm vụ, yêu cầu, mức lương (hiển thị công khai hoặc thỏa thuận).
    *   **Form ứng tuyển nhanh:** Nơi ứng viên điền Họ tên, Số điện thoại, Email, Vị trí ứng tuyển và upload file CV trực tiếp (lưu vào bộ nhớ Cloud của hệ thống).

#### C. Trang điều khoản dịch vụ (Terms of Service)
*   **Mục đích:** Văn bản pháp lý quy định quyền lợi và nghĩa vụ của người dùng khi truy cập website và sử dụng dịch vụ của ZONESPORT.
*   **Chi tiết giao diện:**
    *   Bố cục danh mục bên trái (Sidebar) để người dùng dễ dàng chuyển đổi giữa các mục điều khoản.
    *   **Nội dung chính:** Quy định về bản quyền hình ảnh sản phẩm, quy tắc đăng ký tài khoản, điều kiện hủy đơn hàng, trách nhiệm đối với lịch đặt sân bị hủy do yếu tố bất khả kháng.
    *   Ngày cập nhật điều khoản gần nhất ở đầu trang để đảm bảo tính minh bạch pháp lý.

#### D. Trang chính sách bảo mật (Privacy Policy)
*   **Mục đích:** Cam kết bảo mật thông tin cá nhân của khách hàng khi thu thập qua đăng ký tài khoản, thanh toán online và chấm công.
*   **Chi tiết giao diện:**
    *   **Nội dung chính:** Các loại thông tin thu thập (Họ tên, Sđt, Email, IP định vị chấm công, Cookies trình duyệt), mục đích sử dụng (giao hàng, gửi thông tin khuyến mãi, xác minh vị trí chấm công nhân viên), cam kết không bán dữ liệu cho bên thứ ba.
    *   Quy trình người dùng có thể yêu cầu xóa dữ liệu cá nhân khỏi hệ thống ZONESPORT.

#### E. Trang hỏi đáp thường gặp (FAQs)
*   **Mục đích:** Giải đáp nhanh các thắc mắc phổ biến của khách hàng, giảm tải cho bộ phận Chăm sóc khách hàng (Live chat).
*   **Chi tiết giao diện:**
    *   **Thanh tìm kiếm câu hỏi:** Hỗ trợ tìm nhanh câu hỏi bằng từ khóa.
    *   **Phân nhóm câu hỏi:** Chia thành các nhóm: "Mua hàng & Giao nhận", "Đặt lịch sân cầu lông", "Thanh toán & Hoàn tiền", "Tiếp thị liên kết (Affiliate)".
    *   **Bố cục Accordion:** Thiết kế đóng/mở nội dung câu trả lời khi click vào tiêu đề câu hỏi để trang web gọn gàng, tăng trải nghiệm người dùng.

#### F. Trang chính sách đổi trả hàng (Return & Refund Policy)
*   **Mục đích:** Hướng dẫn quy trình đổi size, đổi mẫu sản phẩm hoặc hoàn tiền khi hàng bị lỗi.
*   **Chi tiết giao diện:**
    *   **Điều kiện đổi trả:** Quy định thời gian đổi trả (ví dụ: 7 ngày kể từ khi nhận hàng), trạng thái sản phẩm yêu cầu (còn nguyên tag, chưa qua sử dụng, vợt chưa căng dây hoặc chưa bóc seal cán).
    *   **Quy trình thực hiện:** Hướng dẫn tạo yêu cầu đổi trả online tại trang quản lý đơn hàng của user hoặc mang trực tiếp ra cửa hàng gần nhất.
    *   **Thời gian hoàn tiền:** Thời gian xử lý hoàn trả tiền mặt hoặc hoàn qua ví MoMo/VNPay (từ 3 - 5 ngày làm việc).

#### G. Trang hướng dẫn mua hàng & Đặt sân (User Guide)
*   **Mục đích:** Chỉ dẫn từng bước bằng hình ảnh/video để khách hàng mới dễ dàng thực hiện mua sắm sản phẩm hoặc đặt sân cầu lông online.
*   **Chi tiết giao diện:**
    *   **Tab chuyển đổi:** Tab 1 - "Hướng dẫn mua hàng E-commerce", Tab 2 - "Hướng dẫn Đặt sân trực tuyến".
    *   **Các bước trực quan:** Minh họa bằng sơ đồ các bước (chọn sản phẩm -> thêm giỏ -> nhập voucher -> chọn thanh toán -> nhận mã đơn).
    *   Quy định giữ slot đặt sân tạm thời (10 phút chờ thanh toán), sau 10 phút không thanh toán slot sẽ tự động giải phóng.

---

## PHẦN 2: CÁC MODULE VÀ GIAO DIỆN CÒN THIẾU HOÀN TOÀN (CỰC KỲ QUAN TRỌNG CHO DATN)

Để đồ án tốt nghiệp đạt điểm tối đa và hoàn thiện đúng mô hình kinh doanh kết hợp của ZONESPORT, hệ thống cần bổ sung các phân hệ giao diện sau:

### 2.1 PHÂN HỆ ĐẶT SÂN CẦU LÔNG PHÍA KHÁCH HÀNG (CLIENT BOOKING FLOW)
Bạn đã có menu đặt sân phía Admin nhưng chưa thiết kế luồng đặt sân phía Khách hàng. Đây là luồng nghiệp vụ cốt lõi:

#### 1. Giao diện Danh sách Sân Cầu Lông & Chi nhánh (Courts & Branches List)
*   **Mục đích:** Khách hàng xem danh sách các chi nhánh cụ thể của ZONESPORT để lựa chọn đặt sân gần nhất.
*   **Chi tiết giao diện:**
    *   **Bộ lọc chi nhánh:** Lọc theo quận/huyện, trạng thái sân (còn trống slot nhiều hay ít).
    *   **Danh sách Card Sân:** Mỗi sân hiển thị ảnh thực tế, địa chỉ, số hotline chi nhánh, số lượng sân hiện có, tiện ích đi kèm (có bãi giữ xe ô tô, có căn tin ăn uống, điều hòa, tủ locker khóa mã...).
    *   **Tích hợp Bản đồ:** Bản đồ trực quan (Google Maps) hiển thị ghim vị trí các chi nhánh.

#### 2. Giao diện Chi tiết Sân & Chọn Ca Đặt Sân (Court Booking Grid - Realtime)
*   **Mục đích:** Màn hình chính để người dùng chọn ngày chơi, chọn sân cụ thể và tích chọn các khung giờ mong muốn chơi.
*   **Chi tiết giao diện:**
    *   **Bộ chọn ngày (Date Picker):** Cho phép đặt lịch trước tối đa 7 ngày hoặc 14 ngày.
    *   **Lưới khung giờ (Time Slot Grid):** Hiển thị cột là các Sân (Sân 1, Sân 2, Sân 3...), dòng là các khung giờ hoạt động (ví dụ: 5:00 - 22:00, chia ca mỗi 30 phút hoặc 60 phút).
    *   **Màu sắc trạng thái ca:**
        *   *Màu Xám:* Ca đã có khách đặt và thanh toán (bị khóa, không thể nhấn).
        *   *Màu Cam:* Ca đang có người chọn giữ chỗ tạm thời (đang checkout, khóa tạm 10 phút).
        *   *Màu Xanh lá:* Ca còn trống, sẵn sàng đặt.
        *   *Màu Hồng (Accent):* Ca người dùng đang tự chọn.
    *   **Bảng giá động:** Giá tiền thay đổi trực tiếp trên ô ca tùy thuộc vào khung giờ vàng (16:00 - 22:00 và cuối tuần giá cao hơn giờ hành chính).
    *   **Bộ đếm ngược giữ slot (Lock Timer):** Khi người dùng click chọn slot và bấm "Tiến hành thanh toán", hệ thống sẽ khóa slot đó trong DB và hiển thị đồng hồ đếm ngược 10:00 phút để khách hàng hoàn tất thanh toán.

#### 3. Giao diện Thanh toán Đặt sân (Court Booking Checkout)
*   **Mục đích:** Xác nhận thông tin lịch đặt sân và thanh toán trực tuyến để giữ sân.
*   **Chi tiết giao diện:**
    *   **Tóm tắt đơn đặt:** Chi nhánh, mã sân, ngày đặt, danh sách ca giờ đã chọn, tổng số giờ chơi.
    *   **Lựa chọn hình thức thanh toán:**
        *   *Thanh toán 100%:* Thanh toán toàn bộ tiền sân.
        *   *Đặt cọc (Deposit):* Thanh toán trước 30% hoặc 50% tổng tiền (tùy cấu hình hệ thống), phần còn lại thanh toán tại quầy khi nhận sân.
    *   **Nhập mã Voucher:** Áp dụng mã giảm giá dành riêng cho dịch vụ đặt sân.
    *   **Cổng thanh toán:** Chọn VNPay, MoMo hoặc Chuyển khoản QR ngân hàng (tự động điền cú pháp chuyển khoản chứa mã booking code).

#### 4. Giao diện Quản lý Đặt sân cá nhân (User Booking History)
*   **Mục đích:** Nơi người dùng theo dõi các lịch đặt sân của mình và thực hiện check-in hoặc gửi yêu cầu hủy lịch.
*   **Chi tiết giao diện:**
    *   **Danh sách lịch đặt:** Chia theo tab (Chờ thanh toán, Đã xác nhận, Đang chơi, Đã hoàn thành, Đã hủy).
    *   **Mã QR Check-in:** Mỗi booking thành công có 1 mã QR duy nhất. Khi đến sân, khách hàng chỉ cần đưa mã QR này cho lễ tân quét để xác nhận nhận sân mà không cần đọc tên/sđt.
    *   **Chi tiết hóa đơn:** Hiển thị tiền sân, tiền nước uống/dịch vụ mua thêm (được lễ tân thêm trực tiếp tại quầy trong ca chơi).
    *   **Nút Hủy đặt lịch:** Chỉ hiển thị nếu thời gian hiện tại cách giờ chơi tối thiểu 12 tiếng hoặc 24 tiếng (theo chính sách của shop).

---

### 2.2 PHÂN HỆ TIẾP THỊ LIÊN KẾT PHÍA KHÁCH HÀNG (CLIENT AFFILIATE MODULE)
Nhằm tăng tính thuyết phục của đồ án tốt nghiệp bằng tính năng Marketing nâng cao, hệ thống ZONESPORT có tích hợp hệ thống Tiếp thị liên kết (Affiliate).

#### 1. Giao diện Đăng ký Cộng tác viên (CTV Affiliate)
*   **Mục đích:** Giới thiệu chính sách hoa hồng hấp dẫn của ZONESPORT để người dùng đăng ký làm CTV.
*   **Chi tiết giao diện:**
    *   Bảng tỷ lệ phần trăm hoa hồng theo danh mục sản phẩm (ví dụ: Vợt cầu lông 5%, Giày cầu lông 7%, Phụ kiện 10%).
    *   Nút bấm "Đăng ký tham gia ngay" (Chỉ hiển thị nếu tài khoản đã xác thực thông tin cá nhân).

#### 2. Giao diện Trang quản lý CTV (Affiliate Dashboard)
*   **Mục đích:** Nơi CTV theo dõi hiệu quả tiếp thị của mình.
*   **Chi tiết giao diện:**
    *   **Thống kê tổng quan:** Số lượt click vào link ref, số đơn hàng phát sinh thành công từ link ref, số dư hoa hồng khả dụng (đã được duyệt rút), số dư hoa hồng tạm tính (đang chờ đơn hàng hoàn thành).
    *   **Biểu đồ hiệu suất:** Biểu đồ đường (Line Chart) thể hiện số click và hoa hồng theo ngày/tháng.
    *   **Lịch sử chuyển đổi (Affiliate Conversions):** Danh sách các đơn hàng mua qua link tiếp thị, trạng thái đơn hàng (Đang giao -> hoa hồng chờ; Đã hoàn thành -> hoa hồng được duyệt; Đã hủy -> hoa hồng bị hủy).

#### 3. Giao diện Công cụ tạo Link Tiếp thị (Affiliate Link Generator)
*   **Mục đích:** Giúp CTV tự tạo link ref của bất kỳ sản phẩm nào trên shop.
*   **Chi tiết giao diện:**
    *   Mã giới thiệu cố định của CTV (`affiliate_code`).
    *   **Form tạo link:** Ô dán link sản phẩm gốc của shop (Ví dụ: `https://zonesport.vn/products/vot-yonex-nanoflare-1000z`).
    *   Nút "Tạo Link Tiếp Thị" -> Hệ thống tự động nối đuôi ref để trả về link mới dạng: `https://zonesport.vn/products/vot-yonex-nanoflare-1000z?ref=CTV123`. CTV chỉ cần click nút "Sao chép" để đi chia sẻ lên Facebook, Tiktok, Zalo.

#### 4. Giao diện Yêu cầu Rút tiền (Withdrawal Request)
*   **Mục đích:** CTV làm lệnh rút tiền hoa hồng tích lũy về tài khoản ngân hàng cá nhân.
*   **Chi tiết giao diện:**
    *   Hiển thị số tiền khả dụng hiện tại. Quy định số tiền tối thiểu được rút mỗi lần (ví dụ: 200,000 VND).
    *   **Form nhập thông tin:** Chọn Tên ngân hàng nhận, số tài khoản, tên chủ thẻ (viết hoa không dấu), số tiền cần rút.
    *   Lịch sử các yêu cầu rút tiền trước đó và trạng thái (Đang chờ duyệt, Đã chuyển khoản thành công, Từ chối kèm lý do).

---

### 2.3 PHÂN HỆ QUẢN TRỊ ADMIN BỔ SUNG (ADMIN DASHBOARD SUPPLEMETARY SCREENS)

#### 1. Giao diện Quản lý Sân Cầu Lông (Admin Court & Schedules Price Management)
*   **Mục đích:** Thiết lập hệ thống đặt sân đầu vào cho Admin.
*   **Chi tiết giao diện:**
    *   **Quản lý danh sách sân:** Form điền tên sân, mã sân (tự sinh QR định danh sân), loại sân, trạng thái hoạt động.
    *   **Thiết lập Ca hoạt động:** Giao diện cài đặt giờ mở/đóng cửa và chia ca mặc định (ví dụ: Khung ca 60 phút: Ca 1 từ 5:00 - 6:00, Ca 2 từ 6:00 - 7:00...).
    *   **Cấu hình bảng giá động:** Form thiết lập khoảng giờ nào thuộc "Giờ thường", khoảng giờ nào thuộc "Giờ cao điểm / Giờ vàng" và áp mức giá cụ thể theo từng loại sân (VIP vs Thường) và ngày chơi (Weekday vs Weekend).

#### 2. Giao diện Bảng điều khiển Lịch đặt sân (Booking Calendar Matrix View)
*   **Mục đích:** Giao diện cốt lõi cho Admin/Lễ tân chi nhánh điều phối lịch sân hàng ngày.
*   **Chi tiết giao diện:**
    *   Hiển thị lưới toàn bộ các sân theo thời gian thực (Realtime). Sử dụng công nghệ WebSocket (Laravel Reverb) để tự động cập nhật trạng thái khi có khách hàng đặt sân online mà lễ tân không cần tải lại trang.
    *   Hỗ trợ thao tác kéo-thả (Drag & Drop) hoặc click vào ô trống để tạo nhanh đơn đặt lịch trực tiếp cho khách gọi điện hoặc khách vãng lai đến quầy.
    *   Hỗ trợ chuyển đổi nhanh vị trí sân cho khách trong trường hợp xảy ra sự cố kỹ thuật tại sân cũ.

#### 3. Giao diện Lễ tân Check-in & Ghi nhận Dịch vụ phát sinh
*   **Mục đích:** Nhân viên đón khách tại quầy, xác nhận lịch đặt và ghi nhận dịch vụ phụ trợ phát sinh trong ca chơi.
*   **Chi tiết giao diện:**
    *   **Quét mã QR đặt lịch:** Nhận mã QR từ điện thoại khách qua camera hoặc máy quét chuyên dụng để tự động tìm kiếm booking.
    *   Nút bấm chuyển trạng thái booking sang `checked_in` và `playing`.
    *   **Menu Dịch vụ nhanh (POS Sân):** Hiển thị các sản phẩm dịch vụ đi kèm (nước suối, nước ngọt, thuê vợt, mua hộp cầu, thuê giày...). Khi khách lấy đồ, lễ tân click chọn dịch vụ để tự động cộng dồn vào hóa đơn của sân đó.
    *   **Thanh toán hóa đơn tổng (Checkout):** Khi kết thúc ca chơi, hệ thống tổng hợp tiền sân còn lại + tiền dịch vụ phát sinh -> xuất hóa đơn và in phiếu thu (hỗ trợ in nhiệt hóa đơn mini 80mm).

#### 4. Giao diện Quản lý Lịch bảo trì Sân (Court Maintenance Calendar)
*   **Mục đích:** Block sân khi cần sửa chữa thiết bị, lau sàn gỗ, thay thảm hoặc bảo dưỡng hệ thống chiếu sáng.
*   **Chi tiết giao diện:**
    *   Lịch tháng trực quan. Khi tạo lịch bảo trì cho Sân A từ ngày X đến ngày Y, hệ thống tự động khóa tất cả các slot trống của Sân A trong khoảng thời gian đó, ngăn không cho khách hàng đặt online.
    *   Tự động gửi thông báo email/hủy lịch và hoàn cọc cho những khách hàng đã lỡ đặt sân trúng lịch bảo trì đột xuất.

#### 5. Giao diện Quản lý Cửa hàng & Cấu hình Chi nhánh
*   **Mục đích:** Thiết lập cấu hình hệ thống các chi nhánh tích hợp chấm công GPS/Wifi.
*   **Chi tiết giao diện:**
    *   Form điền thông tin chi nhánh: Tên chi nhánh, Địa chỉ chi tiết, Hotline liên hệ.
    *   **Bản đồ định vị GPS:** Chọn chính xác tọa độ Vĩ độ (Latitude) và Kinh độ (Longitude) của chi nhánh để làm mốc tính khoảng cách check-in chấm công cho nhân viên.
    *   **Cấu hình IP & Wifi:** Điền địa chỉ IP tĩnh của mạng internet cửa hàng và địa chỉ MAC (BSSID) của bộ phát Wifi. Nhân viên bắt buộc phải kết nối đúng Wifi này mới được ghi nhận chấm công thành công.

#### 6. Giao diện Quản lý Nhập kho & Lịch sử Biến động Kho
*   **Mục đích:** Theo dõi chặt chẽ dòng đời sản phẩm và số lượng tồn kho của shop thể thao.
*   **Chi tiết giao diện:**
    *   **Tạo Phiếu Nhập Kho (Purchase Order):** Form chọn Nhà cung cấp, chọn danh sách các biến thể sản phẩm cần nhập, điền đơn giá nhập (Cost Price - để tính lợi nhuận ròng) và số lượng nhập. Xác nhận nhập kho sẽ tự động tăng số lượng `stock` trong bảng `product_variants`.
    *   **Giao diện Tra cứu Biến động Kho (Inventory Audit Log):** Bảng hiển thị lịch sử tất cả các giao dịch kho (`inventory_transactions`). Cho phép lọc theo mã SKU sản phẩm, loại giao dịch (Nhập kho, xuất đơn hàng, hủy giữ chỗ, điều chỉnh kho lỗi), nhân viên thực hiện, thời gian biến động. Giúp ngăn chặn thất thoát hàng hóa.

#### 7. Giao diện Quản lý Affiliate & Duyệt Yêu cầu Rút tiền
*   **Mục đích:** Vận hành hệ thống tiếp thị liên kết từ phía Admin.
*   **Chi tiết giao diện:**
    *   **Quản lý chiến dịch & tỷ lệ hoa hồng:** Thiết lập tỷ lệ hoa hồng chung hoặc áp tỷ lệ hoa hồng đặc biệt cho từng sản phẩm khó bán cần đẩy mạnh doanh số.
    *   **Quản lý danh sách CTV:** Xem danh sách tài khoản đã đăng ký affiliate, phê duyệt hoặc khóa quyền CTV nếu phát hiện hành vi gian lận.
    *   **Duyệt lệnh rút tiền (Withdrawal Request Manager):** Hiển thị danh sách các CTV gửi yêu cầu rút tiền. Cung cấp nút bấm "Tải danh sách chuyển khoản ngân hàng hàng loạt (Excel/CSV)" để Admin chuyển tiền qua ngân hàng, sau đó click "Đã thanh toán" để trừ số dư ví của CTV và gửi thông báo thành công.

#### 8. Giao diện Phân quyền Nhân viên chi tiết (ACL Permission Matrix)
*   **Mục đích:** Thiết lập ma trận quyền hạn cho từng nhân viên, đảm bảo tính bảo mật dữ liệu.
*   **Chi tiết giao diện:**
    *   **Bảng ma trận phân quyền (Permission Grid):**
        *   Cột dọc là danh sách các quyền chi tiết (Ví dụ: `Xem sản phẩm`, `Thêm sản phẩm`, `Xóa sản phẩm`, `Xem đơn hàng`, `Duyệt hoàn tiền`, `Xem chấm công nhân viên khác`...).
        *   Hàng ngang là các chức danh / vai trò (`admin`, `staff` - nhân viên kho/căng vợt, `seller` - nhân viên lễ tân đặt sân/POS).
    *   Admin tích chọn các quyền tương ứng cho từng vai trò. Khi nhân viên đăng nhập, hệ thống sẽ ẩn các menu không thuộc quyền hạn truy cập của họ.

#### 9. Giao diện Báo cáo Thống kê Doanh thu & Hiệu suất hỗn hợp (Analytics Dashboard)
*   **Mục đích:** Màn hình quan trọng nhất giúp chủ doanh nghiệp có cái nhìn toàn cảnh về tình hình kinh doanh của thương hiệu ZONESPORT.
*   **Chi tiết giao diện:**
    *   **Bộ lọc thời gian và chi nhánh:** Lọc báo cáo theo ngày, tuần, tháng, quý hoặc lọc dữ liệu của riêng từng chi nhánh sân/cửa hàng.
    *   **Các chỉ số đo lường hiệu suất chính (KPIs):**
        *   Tổng doanh thu kết hợp.
        *   Tỷ trọng doanh thu: Phân tích tỷ lệ % doanh thu đến từ e-commerce (bán vợt, phụ kiện trực tuyến), POS (bán tại quầy) và Đặt sân cầu lông (tiền thuê sân + tiền nước uống/dịch vụ đi kèm).
        *   Tỷ lệ lấp đầy sân (Court Occupancy Rate): Phần trăm số giờ sân hoạt động thực tế trên tổng số giờ mở cửa để đánh giá hiệu suất khai thác của từng sân.
    *   **Biểu đồ so sánh:** Biểu đồ cột chồng thể hiện sự tăng trưởng doanh thu bán hàng và đặt sân qua các tháng.
    *   **Báo cáo lợi nhuận ròng:** Doanh thu trừ đi giá vốn nhập hàng, tiền hoa hồng affiliate đã duyệt và các chi phí vận hành khác.
