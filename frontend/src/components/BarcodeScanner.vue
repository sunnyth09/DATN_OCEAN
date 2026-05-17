<script setup>
import { onMounted, onUnmounted } from "vue";
import { Html5QrcodeScanner } from "html5-qrcode";

const emit = defineEmits(["scan-success"]);

onMounted(() => {
    const scanner = new Html5QrcodeScanner(
        "reader",
        { fps: 10, qrbox: { width: 250, height: 150 } },
        /* verbose= */ false,
    );

    scanner.render(onScanSuccess, onScanFailure);

    function onScanSuccess(decodedText, decodedResult) {
        // Phát âm thanh bíp
        document.getElementById("beep-sound").play();

        // Gửi mã vạch quét được lên component cha (ví dụ: màn hình POS)
        emit("scan-success", decodedText);

        // Tạm dừng scan vài giây để tránh quét 1 sản phẩm nhiều lần
        scanner.pause(true);
        setTimeout(() => scanner.resume(), 2000);
    }

    function onScanFailure(error) {
        // Bỏ qua lỗi vì nó sẽ liên tục báo lỗi khi chưa tìm thấy mã
    }
});
</script>
<template>
    <div>
        <div id="reader" width="600px"></div>
        <!-- Thẻ audio ẩn để phát tiếng "bíp" -->
        <audio id="beep-sound" src="/sounds/beep.mp3" preload="auto"></audio>
    </div>
</template>
