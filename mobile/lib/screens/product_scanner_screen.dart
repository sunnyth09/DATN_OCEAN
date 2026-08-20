import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

import '../config/app_theme.dart';
import '../services/api_client.dart';
import '../widgets/app_toast.dart';

/// Màn hình Quét mã sản phẩm / Tìm kiếm bằng Camera (Shopee Lens & TikTok Shop style)
class ProductScannerScreen extends StatefulWidget {
  const ProductScannerScreen({super.key});

  @override
  State<ProductScannerScreen> createState() => _ProductScannerScreenState();
}

class _ProductScannerScreenState extends State<ProductScannerScreen>
    with SingleTickerProviderStateMixin {
  late final MobileScannerController _scannerController;
  late final AnimationController _laserAnimController;
  late final Animation<double> _laserAnimation;

  bool _isProcessing = false;
  bool _isTorchOn = false;
  bool _isFrontCamera = false;
  String? _lastScannedCode;

  @override
  void initState() {
    super.initState();
    _scannerController = MobileScannerController(
      detectionSpeed: DetectionSpeed.noDuplicates,
      facing: CameraFacing.back,
      returnImage: false,
    );

    _laserAnimController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 2000),
    )..repeat(reverse: true);

    _laserAnimation = Tween<double>(begin: 0.05, end: 0.95).animate(
      CurvedAnimation(
        parent: _laserAnimController,
        curve: Curves.easeInOut,
      ),
    );
  }

  @override
  void dispose() {
    _laserAnimController.dispose();
    _scannerController.dispose();
    super.dispose();
  }

  Future<void> _handleBarcodeDetected(BarcodeCapture capture) async {
    if (_isProcessing) return;

    final List<Barcode> barcodes = capture.barcodes;
    if (barcodes.isEmpty) return;

    final String code = barcodes.first.rawValue?.trim() ?? '';
    if (code.isEmpty || code == _lastScannedCode) return;

    _lastScannedCode = code;
    await _searchProductByBarcode(code);
  }

  Future<void> _searchProductByBarcode(String barcode) async {
    if (!mounted) return;
    setState(() => _isProcessing = true);
    HapticFeedback.mediumImpact();

    try {
      final response = await ApiClient().dio.get(
        '/products',
        queryParameters: {'search': barcode, 'limit': 5},
      );

      if (!mounted) return;

      List<dynamic> productList = [];
      final resData = response.data;
      if (resData is Map && resData['data'] is List) {
        productList = resData['data'];
      } else if (resData is List) {
        productList = resData;
      }

      if (productList.isNotEmpty) {
        if (productList.length == 1) {
          // Khớp đúng 1 sản phẩm -> Mở trực tiếp trang Chi tiết sản phẩm
          final product = Map<String, dynamic>.from(productList.first);
          AppToast.showSuccess(
            context,
            message: 'Đã tìm thấy: ${product['name'] ?? 'Sản phẩm'}',
          );
          if (mounted) {
            context.pushReplacement('/product-detail', extra: product);
          }
        } else {
          // Nhiều sản phẩm -> Mở danh sách tìm kiếm
          AppToast.showSuccess(
            context,
            message: 'Tìm thấy ${productList.length} sản phẩm phù hợp!',
          );
          if (mounted) {
            context.pushReplacement(
              '/product-list',
              extra: {'searchQuery': barcode},
            );
          }
        }
      } else {
        // Không tìm thấy sản phẩm
        _showNotFoundDialog(barcode);
      }
    } catch (e) {
      if (mounted) {
        _showNotFoundDialog(barcode);
      }
    } finally {
      if (mounted) {
        setState(() => _isProcessing = false);
      }
    }
  }

  void _showNotFoundDialog(String barcode) {
    showModalBottomSheet(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (ctx) => Container(
        padding: const EdgeInsets.fromLTRB(20, 20, 20, 28),
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 44,
              height: 4,
              decoration: BoxDecoration(
                color: const Color(0xFFE2E8F0),
                borderRadius: BorderRadius.circular(2),
              ),
            ),
            const SizedBox(height: 18),
            Container(
              width: 56,
              height: 56,
              decoration: BoxDecoration(
                color: const Color(0xFFFFF1F2),
                shape: BoxShape.circle,
              ),
              child: const Icon(
                Icons.search_off_rounded,
                color: AppColors.primary,
                size: 30,
              ),
            ),
            const SizedBox(height: 14),
            const Text(
              'Chưa tìm thấy sản phẩm',
              style: TextStyle(
                fontSize: 17,
                fontWeight: FontWeight.w800,
                color: AppColors.textPrimary,
              ),
            ),
            const SizedBox(height: 6),
            Text(
              'Không tìm thấy sản phẩm nào có mã "$barcode". Bạn có thể thử tìm kiếm bằng từ khóa trên thanh tìm kiếm.',
              textAlign: TextAlign.center,
              style: const TextStyle(
                fontSize: 13,
                color: AppColors.textSecondary,
                height: 1.4,
              ),
            ),
            const SizedBox(height: 20),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton(
                    onPressed: () {
                      Navigator.pop(ctx);
                      _lastScannedCode = null;
                    },
                    style: OutlinedButton.styleFrom(
                      padding: const EdgeInsets.symmetric(vertical: 12),
                      side: const BorderSide(color: Color(0xFFCBD5E1)),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
                    ),
                    child: const Text(
                      'Quét lại',
                      style: TextStyle(
                        fontWeight: FontWeight.w700,
                        color: AppColors.textPrimary,
                      ),
                    ),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: ElevatedButton(
                    onPressed: () {
                      Navigator.pop(ctx);
                      context.pushReplacement('/search');
                    },
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppColors.primary,
                      padding: const EdgeInsets.symmetric(vertical: 12),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
                    ),
                    child: const Text(
                      'Tìm bằng chữ',
                      style: TextStyle(
                        fontWeight: FontWeight.w700,
                        color: Colors.white,
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _pickImageFromGallery() async {
    try {
      final picker = ImagePicker();
      final image = await picker.pickImage(source: ImageSource.gallery);
      if (image == null) return;

      setState(() => _isProcessing = true);
      final BarcodeCapture? capture =
          await _scannerController.analyzeImage(image.path);

      if (capture != null && capture.barcodes.isNotEmpty) {
        final code = capture.barcodes.first.rawValue?.trim() ?? '';
        if (code.isNotEmpty) {
          await _searchProductByBarcode(code);
          return;
        }
      }

      if (mounted) {
        setState(() => _isProcessing = false);
        AppToast.showWarning(
          context,
          message: 'Không tìm thấy mã vạch trong ảnh đã chọn!',
        );
      }
    } catch (e) {
      if (mounted) {
        setState(() => _isProcessing = false);
        AppToast.showError(
          context,
          message: 'Không thể phân tích ảnh đã chọn.',
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final size = MediaQuery.of(context).size;
    final scanAreaSize = size.width * 0.72;

    return Scaffold(
      backgroundColor: Colors.black,
      body: Stack(
        children: [
          // 1. Mobile Camera Scanner
          MobileScanner(
            controller: _scannerController,
            onDetect: _handleBarcodeDetected,
          ),

          // 2. Dark Overlay with Cutout Mask
          CustomPaint(
            size: size,
            painter: _ScannerOverlayPainter(
              scanAreaSize: scanAreaSize,
              borderRadius: 20,
            ),
          ),

          // 3. Scanning Frame & Animated Laser
          Center(
            child: SizedBox(
              width: scanAreaSize,
              height: scanAreaSize,
              child: Stack(
                children: [
                  // Corner Brackets
                  Positioned.fill(
                    child: CustomPaint(
                      painter: _ScannerCornersPainter(
                        color: AppColors.primary,
                        cornerLength: 28,
                        strokeWidth: 4,
                      ),
                    ),
                  ),

                  // Animated Laser Line
                  AnimatedBuilder(
                    animation: _laserAnimation,
                    builder: (context, child) {
                      return Positioned(
                        top: scanAreaSize * _laserAnimation.value,
                        left: 12,
                        right: 12,
                        child: Container(
                          height: 2.5,
                          decoration: BoxDecoration(
                            gradient: const LinearGradient(
                              colors: [
                                Colors.transparent,
                                AppColors.primary,
                                Color(0xFFFF85A1),
                                AppColors.primary,
                                Colors.transparent,
                              ],
                            ),
                            boxShadow: [
                              BoxShadow(
                                color: AppColors.primary.withValues(alpha: 0.8),
                                blurRadius: 10,
                                spreadRadius: 2,
                              ),
                            ],
                          ),
                        ),
                      );
                    },
                  ),
                ],
              ),
            ),
          ),

          // 4. Top Action Bar
          SafeArea(
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  _buildCircleBtn(
                    icon: Icons.arrow_back_rounded,
                    onTap: () {
                      if (context.canPop()) {
                        context.pop();
                      } else {
                        context.go('/home');
                      }
                    },
                  ),
                  const Text(
                    'Quét mã sản phẩm',
                    style: TextStyle(
                      fontSize: 17,
                      fontWeight: FontWeight.w800,
                      color: Colors.white,
                      letterSpacing: 0.3,
                    ),
                  ),
                  _buildCircleBtn(
                    icon: _isTorchOn
                        ? Icons.flash_on_rounded
                        : Icons.flash_off_rounded,
                    color: _isTorchOn ? const Color(0xFFFFD166) : Colors.white,
                    onTap: () async {
                      await _scannerController.toggleTorch();
                      setState(() => _isTorchOn = !_isTorchOn);
                    },
                  ),
                ],
              ),
            ),
          ),

          // 5. Instruction & Bottom Controls
          Positioned(
            bottom: 0,
            left: 0,
            right: 0,
            child: SafeArea(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(24, 0, 24, 28),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    // Hint Card
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 18,
                        vertical: 12,
                      ),
                      decoration: BoxDecoration(
                        color: Colors.black.withValues(alpha: 0.65),
                        borderRadius: BorderRadius.circular(20),
                        border: Border.all(
                          color: Colors.white.withValues(alpha: 0.15),
                        ),
                      ),
                      child: const Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(
                            Icons.qr_code_scanner_rounded,
                            color: Color(0xFFFFD166),
                            size: 20,
                          ),
                          SizedBox(width: 10),
                          Flexible(
                            child: Text(
                              'Hướng camera vào mã vạch hoặc mã QR trên sản phẩm',
                              textAlign: TextAlign.center,
                              style: TextStyle(
                                color: Colors.white,
                                fontSize: 12.5,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),

                    const SizedBox(height: 24),

                    // Quick Actions (Gallery, Switch Camera)
                    Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        _buildBottomAction(
                          icon: Icons.photo_library_rounded,
                          label: 'Chọn ảnh',
                          onTap: _pickImageFromGallery,
                        ),
                        const SizedBox(width: 36),
                        _buildBottomAction(
                          icon: Icons.flip_camera_ios_rounded,
                          label: 'Đổi camera',
                          onTap: () async {
                            await _scannerController.switchCamera();
                            setState(() => _isFrontCamera = !_isFrontCamera);
                          },
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
          ),

          // 6. Loading Indicator when searching
          if (_isProcessing)
            Container(
              color: Colors.black.withValues(alpha: 0.6),
              child: const Center(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    CircularProgressIndicator(
                      color: AppColors.primary,
                      strokeWidth: 3,
                    ),
                    SizedBox(height: 16),
                    Text(
                      'Đang tìm kiếm sản phẩm...',
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 14,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
              ),
            ),
        ],
      ),
    );
  }

  Widget _buildCircleBtn({
    required IconData icon,
    required VoidCallback onTap,
    Color color = Colors.white,
  }) {
    return GestureDetector(
      onTap: () {
        HapticFeedback.lightImpact();
        onTap();
      },
      child: Container(
        width: 42,
        height: 42,
        decoration: BoxDecoration(
          color: Colors.black.withValues(alpha: 0.45),
          shape: BoxShape.circle,
          border: Border.all(color: Colors.white.withValues(alpha: 0.2)),
        ),
        child: Icon(icon, color: color, size: 20),
      ),
    );
  }

  Widget _buildBottomAction({
    required IconData icon,
    required String label,
    required VoidCallback onTap,
  }) {
    return GestureDetector(
      onTap: () {
        HapticFeedback.lightImpact();
        onTap();
      },
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 52,
            height: 52,
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.15),
              shape: BoxShape.circle,
              border: Border.all(color: Colors.white.withValues(alpha: 0.3)),
            ),
            child: Icon(icon, color: Colors.white, size: 24),
          ),
          const SizedBox(height: 8),
          Text(
            label,
            style: const TextStyle(
              color: Colors.white,
              fontSize: 12,
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
      ),
    );
  }
}

/// Vẽ lớp tối xung quanh ô quét
class _ScannerOverlayPainter extends CustomPainter {
  final double scanAreaSize;
  final double borderRadius;

  _ScannerOverlayPainter({
    required this.scanAreaSize,
    required this.borderRadius,
  });

  @override
  void paint(Canvas canvas, Size size) {
    final backgroundPath = Path()
      ..addRect(Rect.fromLTWH(0, 0, size.width, size.height));

    final scanRect = Rect.fromCenter(
      center: Offset(size.width / 2, size.height / 2),
      width: scanAreaSize,
      height: scanAreaSize,
    );

    final cutoutPath = Path()
      ..addRRect(
        RRect.fromRectAndRadius(scanRect, Radius.circular(borderRadius)),
      );

    final overlayPath = Path.combine(
      PathOperation.difference,
      backgroundPath,
      cutoutPath,
    );

    final paint = Paint()
      ..color = Colors.black.withValues(alpha: 0.55)
      ..style = PaintingStyle.fill;

    canvas.drawPath(overlayPath, paint);
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}

/// Vẽ 4 góc khung quét
class _ScannerCornersPainter extends CustomPainter {
  final Color color;
  final double cornerLength;
  final double strokeWidth;

  _ScannerCornersPainter({
    required this.color,
    required this.cornerLength,
    required this.strokeWidth,
  });

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = color
      ..strokeWidth = strokeWidth
      ..style = PaintingStyle.stroke
      ..strokeCap = StrokeCap.round;

    final w = size.width;
    final h = size.height;

    // Top-Left
    canvas.drawLine(const Offset(0, 0), Offset(cornerLength, 0), paint);
    canvas.drawLine(const Offset(0, 0), Offset(0, cornerLength), paint);

    // Top-Right
    canvas.drawLine(Offset(w, 0), Offset(w - cornerLength, 0), paint);
    canvas.drawLine(Offset(w, 0), Offset(w, cornerLength), paint);

    // Bottom-Left
    canvas.drawLine(Offset(0, h), Offset(cornerLength, h), paint);
    canvas.drawLine(Offset(0, h), Offset(0, h - cornerLength), paint);

    // Bottom-Right
    canvas.drawLine(Offset(w, h), Offset(w - cornerLength, h), paint);
    canvas.drawLine(Offset(w, h), Offset(w, h - cornerLength), paint);
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}
