<body>
    <div>
        <p>Hôm nay, ngày {{ $data['time'] }}.</p>
        <p>Bạn <b>{{ $data['user_name'] }}</b> <b>{{ $data['is_confirm'] }}</b> {{ $data['asset_count'] }} thiết bị <b>{{ $data['asset_name'] }}.</b></p>
        <p>{{ $data['reason'] }}.</p>
    </div>
</body>