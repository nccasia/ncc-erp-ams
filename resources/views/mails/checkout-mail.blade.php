<body>
    <div>
        <p>Hôm nay, ngày {{ $data['time'] }}.</p>
        <p>Tại: Công Ty Cổ Phần NCC Plus Việt Nam</p>
        <p>Địa chỉ: {{ $data['location_address'] }}</p>
        <p>Bộ phận IT có thu hồi từ bạn <b>{{ $data['user_name'] }}</b> {{ $data['count'] }} thiết bị <b>{{ $data['asset_name'] }}</b></p>
        <p><b>Quy định về việc sử dụng thiết bị</b></p>
        <p> - Không được tự ý mang thiết bị được bàn giao ra khỏi công ty nếu chưa có sự đồng ý bằng văn bản của bộ phận IT.</p>
        <p> - Trường hợp thiết bị lỗi do nhà sản xuất, vui lòng báo lại bộ phận IT để được hỗ trợ bảo hành.</p>
        <p> - Trường hợp thiết bị hỏng hóc do cá nhân sử dụng, IT không hỗ trợ bảo hành. Sẽ áp dụng hình thức phạt cá nhân chịu trách nhiệm sửa chữa hoặc đền bù.</p>
        <p> - Nếu nhân viên nghỉ việc phải có trách nhiệm bàn giao lại đầy đủ và trong tình trạng nguyên vẹn những thiết bị trên cho công ty và báo lại bộ phận IT để làm biên bản thu hồi.</p>
        <p><b>Điều khoản chung:</b></p>
        <p> - Nhân viên xác nhận đã nhận đủ số thiết bị như nêu trên, cam kết bảo quản, sử dụng đúng mục đích, theo yêu cầu của công ty.</p>
        <p><i>Bạn vui lòng xác nhận bằng cách: </i><a href="{{ $data['link'] }}"><b>Nhấp vào đường dẫn này!</b></a></p>
        
    </div>
</body>