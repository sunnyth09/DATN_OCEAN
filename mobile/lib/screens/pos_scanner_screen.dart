import 'dart:convert';
import '../config/app_theme.dart';
import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import '../services/api_client.dart';

class PosScannerScreen extends StatefulWidget {
  const PosScannerScreen({super.key});

  @override
  State<PosScannerScreen> createState() => _PosScannerScreenState();
}

class _PosScannerScreenState extends State<PosScannerScreen> {
  String? sessionId;
  bool isProcessing = false;

  final MobileScannerController scannerController = MobileScannerController(
    detectionSpeed: DetectionSpeed.noDuplicates,
    facing: CameraFacing.back,
    returnImage: false,
  );

  @override
  void dispose() {
    scannerController.dispose();
    super.dispose();
  }

  Future<void> sendBarcodeToWeb(String barcode) async {
    if (sessionId == null) return;
    
    // Disable scanner temporarily
    setState(() { isProcessing = true; });
    
    try {
      final response = await ApiClient().dio.post(
        '/admin/pos/mobile-scan',
        data: {
          'barcode': barcode,
          'session_id': sessionId,
        },
      );

      if (response.statusCode == 200) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text('Đã gửi mã: $barcode'),
              backgroundColor: Colors.green,
              duration: const Duration(seconds: 1),
            ),
          );
        }
      } else {
         if (mounted) {
           ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text('Lỗi gửi mã: ${response.statusCode}'),
              backgroundColor: Colors.red,
              duration: const Duration(seconds: 1),
            ),
          );
         }
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Không thể kết nối đến máy chủ.'),
            backgroundColor: Colors.red,
            duration: Duration(seconds: 1),
          ),
        );
      }
    } finally {
      // Re-enable scanner after a short delay
      if (mounted) {
        await Future.delayed(const Duration(milliseconds: 1000));
        setState(() { isProcessing = false; });
      }
    }
  }

  void handleBarcode(BarcodeCapture capture) {
    if (isProcessing) return;

    final List<Barcode> barcodes = capture.barcodes;
    if (barcodes.isEmpty) return;

    final String code = barcodes.first.rawValue ?? '';
    if (code.isEmpty) return;

    if (sessionId == null) {
      // Expecting a session link QR code
      if (code.startsWith('pos_session:')) {
        setState(() {
          sessionId = code.replaceAll('pos_session:', '');
        });
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Kết nối Web POS thành công. Hãy quét sản phẩm!'),
            backgroundColor: Colors.green,
          ),
        );
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Vui lòng quét mã QR trên màn hình máy tính trước!'),
            duration: Duration(seconds: 2),
          ),
        );
      }
    } else {
      // Already linked, scan products
      sendBarcodeToWeb(code);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Máy Quét POS', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
        backgroundColor: const Color(0xFFE63B6F),
        iconTheme: const IconThemeData(color: Colors.white),
        actions: [
          if (sessionId != null)
            IconButton(
              icon: const Icon(Icons.link_off),
              tooltip: 'Ngắt kết nối POS',
              onPressed: () {
                setState(() { sessionId = null; });
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(content: Text('Đã ngắt kết nối với Web POS.')),
                );
              },
            ),
        ],
      ),
      body: Column(
        children: [
          Container(
            width: double.infinity,
            padding: const EdgeInsets.symmetric(vertical: 16, horizontal: 20),
            color: sessionId == null ? Colors.orange.shade100 : Colors.green.shade100,
            child: Row(
              children: [
                Icon(
                  sessionId == null ? Icons.qr_code_scanner : Icons.barcode_reader,
                  color: sessionId == null ? Colors.orange.shade800 : Colors.green.shade800,
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        sessionId == null ? 'BƯỚC 1: LIÊN KẾT MÁY POS' : 'BƯỚC 2: QUÉT SẢN PHẨM',
                        style: TextStyle(
                          fontWeight: FontWeight.bold,
                          color: sessionId == null ? Colors.orange.shade900 : Colors.green.shade900,
                        ),
                      ),
                      Text(
                        sessionId == null 
                            ? 'Vui lòng quét mã QR trên màn hình máy tính (Web POS) để kết nối.' 
                            : 'Đã sẵn sàng. Quét mã vạch sản phẩm để tự động thêm vào đơn hàng.',
                        style: TextStyle(
                          fontSize: 13,
                          color: sessionId == null ? Colors.orange.shade800 : Colors.green.shade800,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          
          Expanded(
            child: Stack(
              children: [
                MobileScanner(
                  controller: scannerController,
                  onDetect: handleBarcode,
                ),
                // Scanner overlay box
                Center(
                  child: Container(
                    width: 250,
                    height: sessionId == null ? 250 : 150,
                    decoration: BoxDecoration(
                      border: Border.all(color: Colors.white.withOpacity(0.5), width: 3),
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),
                ),
                if (isProcessing)
                  Container(
                    color: Colors.black54,
                    child: const Center(
                      child: CircularProgressIndicator(color: Colors.white),
                    ),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
