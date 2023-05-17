<body>
    <div>
        <p>Hôm nay, ngày {{ $data['time'] }}.</p>
        <p>Tại: Công Ty Cổ Phần NCC Plus Việt Nam</p>
        <p>Bộ phận IT có giao cho bạn <b>{{ $data['user_name'] }}</b> {{ $data['count'] }} key active phần mềm <b>{{ $data['software_name'] }}</b></p>
        <p>key: {{$data['license']}}</p>
        <p><b>Điều khoản chung:</b></p>
        <p> - Nhân viên xác nhận đã nhận key active không chia sẽ với người khác, sử dụng đúng mục đích, theo yêu cầu của công ty.</p>
    </div>
</body>