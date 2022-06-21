<body>
    <div>
        <p>Hôm nay, ngày {{ $data['time'] }}.</p>
        <p>Bạn <b>{{ $data['user_name'] }}</b> xác nhận <b>{{ $data['is_confirm'] }}</b> thiết bị <b>{{ $data['asset_name'] }}.</b></p>
        <p>{{ $data['reason'] }}.</p>
    </div>
</body>