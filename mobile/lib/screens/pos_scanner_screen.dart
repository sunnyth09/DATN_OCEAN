import 'package:go_router/go_router.dart';
import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import '../services/api_client.dart';
import '../widgets/app_toast.dart';

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
          AppToast.showSuccess(
            context,
            message: 'Đã gửi mã: $barcode',
          );
        }
      } else {
        if (mounted) {
          AppToast.showError(
            context,
            message: 'Lỗi gửi mã: ${response.statusCode}',
          );
        }
      }
    } catch (e) {
      if (mounted) {
        AppToast.showError(
          context,
          message: 'Không thể kết nối đến máy chủ.',
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
        AppToast.showSuccess(
          context,
          message: 'Kết nối Web POS thành công. Hãy quét sản phẩm!',
        );
      } else {
        AppToast.showWarning(
          context,
          message: 'Vui lòng quét mã QR trên màn hình máy tính trước!',
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
      body: Stack(
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
                border: Border.all(color: Colors.white.withValues(alpha: 0.5), width: 3),
                borderRadius: BorderRadius.circular(12),
              ),
            ),
          ),
          
          // Instruction overlay
          Positioned(
            top: 0,
            left: 0,
            right: 0,
            child: SafeArea(
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16.0, vertical: 8.0),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Container(
                      decoration: BoxDecoration(
                        color: Colors.black.withValues(alpha: 0.5),
                        shape: BoxShape.circle,
                      ),
                      child: IconButton(
                        icon: const Icon(Icons.arrow_back, color: Colors.white),
                        onPressed: () {
                          if (context.canPop()) {
                            context.pop();
                          } else {
                            context.go('/me');
                          }
                        },
                      ),
                    ),
                    if (sessionId != null)
                      Container(
                        decoration: BoxDecoration(
                          color: Colors.red.withValues(alpha: 0.8),
                          borderRadius: BorderRadius.circular(20),
                        ),
                        child: IconButton(
                          icon: const Icon(Icons.link_off, color: Colors.white),
                          tooltip: 'Ngắt kết nối POS',
                          onPressed: () {
                            setState(() { sessionId = null; });
                            AppToast.showInfo(
                              context,
                              message: 'Đã ngắt kết nối với Web POS.',
                            );
                          },
                        ),
                      ),
                  ],
                ),
              ),
            ),
          ),

          // Bottom Instruction Panel
          Positioned(
            bottom: 30,
            left: 20,
            right: 20,
            child: Container(
              padding: const EdgeInsets.symmetric(vertical: 16, horizontal: 20),
              decoration: BoxDecoration(
                color: sessionId == null ? Colors.orange.shade100 : Colors.green.shade100,
                borderRadius: BorderRadius.circular(16),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.2),
                    blurRadius: 10,
                    offset: const Offset(0, 5),
                  )
                ]
              ),
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
                      mainAxisSize: MainAxisSize.min,
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
    );
  }
}
