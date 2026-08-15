import 'package:flutter/material.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter_svg/flutter_svg.dart';

/// Widget tối ưu hiệu năng cao để hiển thị ảnh từ URL.
/// Tự động giới hạn kích thước decode trong bộ nhớ RAM (memCacheWidth / memCacheHeight)
/// để tránh tràn RAM và loại bỏ giật lag (jank) khi cuộn danh sách lớn.
class NetworkImageWidget extends StatelessWidget {
  final String imageUrl;
  final double? width;
  final double? height;
  final BoxFit fit;
  final Widget? placeholder;
  final Widget? errorWidget;
  final BorderRadius? borderRadius;
  final int? customMemCacheWidth;
  final int? customMemCacheHeight;

  const NetworkImageWidget({
    super.key,
    required this.imageUrl,
    this.width,
    this.height,
    this.fit = BoxFit.cover,
    this.placeholder,
    this.errorWidget,
    this.borderRadius,
    this.customMemCacheWidth,
    this.customMemCacheHeight,
  });

  bool get _isSvg {
    final lower = imageUrl.toLowerCase();
    return lower.endsWith('.svg') || lower.contains('.svg?');
  }

  Widget _buildPlaceholder(BuildContext context) {
    return placeholder ??
        Container(
          width: width,
          height: height,
          color: const Color(0xFFF1F5F9),
          child: const Center(
            child: Icon(
              Icons.image_outlined,
              color: Color(0xFFE2E8F0),
              size: 24,
            ),
          ),
        );
  }

  Widget _buildErrorWidget(BuildContext context) {
    return errorWidget ??
        Container(
          width: width,
          height: height,
          color: const Color(0xFFF1F5F9),
          child: const Center(
            child: Icon(
              Icons.broken_image_outlined,
              color: Color(0xFFCBD5E1),
              size: 28,
            ),
          ),
        );
  }

  @override
  Widget build(BuildContext context) {
    if (imageUrl.isEmpty) {
      return _buildErrorWidget(context);
    }

    Widget image;

    if (_isSvg) {
      image = SvgPicture.network(
        imageUrl,
        width: width,
        height: height,
        fit: fit,
        placeholderBuilder: (ctx) => _buildPlaceholder(ctx),
      );
    } else {
      // Giới hạn decode thumbnail về 350px để GPU/RAM nhẹ tối đa khi cuộn danh sách lớn
      int? memWidth = customMemCacheWidth;
      int? memHeight = customMemCacheHeight;

      if (memWidth == null && memHeight == null) {
        if (width != null && width!.isFinite && width! > 0) {
          memWidth = (width! * 1.5).toInt().clamp(100, 600);
        } else {
          memWidth = 350;
        }

        if (height != null && height!.isFinite && height! > 0) {
          memHeight = (height! * 1.5).toInt().clamp(100, 600);
        }
      }

      image = RepaintBoundary(
        child: CachedNetworkImage(
          imageUrl: imageUrl,
          width: width,
          height: height,
          fit: fit,
          memCacheWidth: memWidth,
          memCacheHeight: memHeight,
          maxWidthDiskCache: 800,
          maxHeightDiskCache: 800,
          filterQuality: FilterQuality.low,
          useOldImageOnUrlChange: true,
          fadeInDuration: const Duration(milliseconds: 90),
          fadeOutDuration: const Duration(milliseconds: 90),
          placeholder: (ctx, url) => _buildPlaceholder(ctx),
          errorWidget: (ctx, url, err) => _buildErrorWidget(ctx),
        ),
      );
    }

    if (borderRadius != null) {
      return ClipRRect(borderRadius: borderRadius!, child: image);
    }

    return image;
  }
}
