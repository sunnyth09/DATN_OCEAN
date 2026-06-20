import 'package:flutter/material.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter_svg/flutter_svg.dart';

/// Widget dùng chung để load ảnh từ URL, hỗ trợ cả SVG và ảnh thường (PNG/JPG/WEBP...).
/// Tự động detect SVG qua đuôi file hoặc Content-Type.
class NetworkImageWidget extends StatelessWidget {
  final String imageUrl;
  final double? width;
  final double? height;
  final BoxFit fit;
  final Widget? placeholder;
  final Widget? errorWidget;
  final BorderRadius? borderRadius;

  const NetworkImageWidget({
    super.key,
    required this.imageUrl,
    this.width,
    this.height,
    this.fit = BoxFit.cover,
    this.placeholder,
    this.errorWidget,
    this.borderRadius,
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
            child: CircularProgressIndicator(
              strokeWidth: 2,
              color: Color(0xFFE63B6F),
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
            child: Icon(Icons.image_not_supported_outlined,
                color: Color(0xFFCBD5E1), size: 36),
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
      image = CachedNetworkImage(
        imageUrl: imageUrl,
        width: width,
        height: height,
        fit: fit,
        placeholder: (ctx, url) => _buildPlaceholder(ctx),
        errorWidget: (ctx, url, err) => _buildErrorWidget(ctx),
      );
    }

    if (borderRadius != null) {
      return ClipRRect(borderRadius: borderRadius!, child: image);
    }

    return image;
  }
}
