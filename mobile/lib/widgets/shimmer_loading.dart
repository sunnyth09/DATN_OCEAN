import 'package:flutter/material.dart';
import 'package:shimmer/shimmer.dart';

class ShimmerLoading extends StatelessWidget {
  const ShimmerLoading({super.key});

  @override
  Widget build(BuildContext context) {
    return Shimmer.fromColors(
      baseColor: Colors.grey.shade300,
      highlightColor: Colors.grey.shade100,
      child: GridView.builder(
        shrinkWrap: true,
        physics: const NeverScrollableScrollPhysics(),
        gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
          crossAxisCount: 2,
          crossAxisSpacing: 16,
          mainAxisSpacing: 16,
          childAspectRatio: 0.65,
        ),
        itemCount: 6, // Hiển thị 6 khung cho lấp đầy màn hình
        itemBuilder: (_, _) => _buildShimmerProductItem(),
      ),
    );
  }

  Widget _buildShimmerProductItem() {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Ảnh Shimmer
          Container(
            height: 160,
            decoration: const BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.only(topLeft: Radius.circular(16), topRight: Radius.circular(16)),
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(12),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Text Shimmer (Dòng 1)
                Container(height: 12, width: double.infinity, color: Colors.white),
                const SizedBox(height: 4),
                // Text Shimmer (Dòng 2)
                Container(height: 12, width: 80, color: Colors.white),
                const SizedBox(height: 12),
                // Text Giá Shimmer
                Container(height: 16, width: 100, color: Colors.white),
              ],
            ),
          )
        ],
      ),
    );
  }
}
class ShimmerBlock extends StatelessWidget {
  final double width;
  final double height;
  final double borderRadius;
  final int count;

  const ShimmerBlock({
    super.key, 
    required this.width, 
    required this.height, 
    this.borderRadius = 8,
    this.count = 1
  });

  @override
  Widget build(BuildContext context) {
    Widget item = Container(
      width: width,
      height: height,
      margin: const EdgeInsets.only(bottom: 12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(borderRadius),
      ),
    );

    return Shimmer.fromColors(
      baseColor: Colors.grey.shade300,
      highlightColor: Colors.grey.shade100,
      child: count > 1 
          ? Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: List.generate(count, (index) => item))
          : item,
    );
  }
}
class SliverShimmerLoading extends StatelessWidget {
  /// Cho phép mỗi màn truyền đúng khung grid của nó để skeleton khớp
  /// layout thật → không nhảy layout (CLS) khi data về.
  final EdgeInsetsGeometry padding;
  final double crossAxisSpacing;
  final double mainAxisSpacing;
  final double childAspectRatio;
  final int itemCount;

  const SliverShimmerLoading({
    super.key,
    this.padding = EdgeInsets.zero,
    this.crossAxisSpacing = 16,
    this.mainAxisSpacing = 16,
    this.childAspectRatio = 0.65,
    this.itemCount = 4,
  });

  @override
  Widget build(BuildContext context) {
    return SliverPadding(
      padding: padding,
      sliver: SliverGrid(
        gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
          crossAxisCount: 2,
          crossAxisSpacing: crossAxisSpacing,
          mainAxisSpacing: mainAxisSpacing,
          childAspectRatio: childAspectRatio,
        ),
        delegate: SliverChildBuilderDelegate(
          (context, index) {
            return Shimmer.fromColors(
              baseColor: Colors.grey.shade300,
               highlightColor: Colors.grey.shade100,
               child: const ShimmerProductItemTemplate()
            );
          },
          childCount: itemCount,
        ),
      ),
    );
  }
}

class ShimmerProductItemTemplate extends StatelessWidget {
  const ShimmerProductItemTemplate({super.key});
  
  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            height: 160,
            decoration: const BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.only(topLeft: Radius.circular(16), topRight: Radius.circular(16)),
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(12),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(height: 12, width: double.infinity, color: Colors.white),
                const SizedBox(height: 4),
                Container(height: 12, width: 80, color: Colors.white),
                const SizedBox(height: 12),
                Container(height: 16, width: 100, color: Colors.white),
              ],
            ),
          )
        ],
      ),
    );
  }
}

class ProductDetailShimmer extends StatelessWidget {
  const ProductDetailShimmer({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        leading: const BackButton(color: Colors.black),
        title: Shimmer.fromColors(
          baseColor: Colors.grey.shade300,
          highlightColor: Colors.grey.shade100,
          child: Container(height: 20, width: 120, color: Colors.white),
        ),
        centerTitle: true,
      ),
      body: Shimmer.fromColors(
        baseColor: Colors.grey.shade300,
        highlightColor: Colors.grey.shade100,
        child: SingleChildScrollView(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                height: 350,
                width: double.infinity,
                color: Colors.white,
              ),
              const SizedBox(height: 16),
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 20),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Container(height: 14, width: 100, color: Colors.white),
                    const SizedBox(height: 8),
                    Container(height: 24, width: double.infinity, color: Colors.white),
                    const SizedBox(height: 4),
                    Container(height: 24, width: 200, color: Colors.white),
                    const SizedBox(height: 16),
                    Container(height: 28, width: 150, color: Colors.white),
                    const SizedBox(height: 24),
                    Container(height: 20, width: 80, color: Colors.white),
                    const SizedBox(height: 12),
                    Row(
                      children: List.generate(
                        4,
                        (index) => Container(
                          margin: const EdgeInsets.only(right: 12),
                          height: 40,
                          width: 40,
                          decoration: const BoxDecoration(
                            color: Colors.white,
                            shape: BoxShape.circle,
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(height: 24),
                    Container(height: 20, width: 100, color: Colors.white),
                    const SizedBox(height: 12),
                    Row(
                      children: List.generate(
                        4,
                        (index) => Container(
                          margin: const EdgeInsets.only(right: 12),
                          height: 40,
                          width: 60,
                          color: Colors.white,
                        ),
                      ),
                    ),
                  ],
                ),
              )
            ],
          ),
        ),
      ),
    );
  }
}

/// Skeleton dạng list cho CustomScrollView (flash sale, các màn dùng sliver).
class SliverListShimmerLoading extends StatelessWidget {
  final int itemCount;
  const SliverListShimmerLoading({super.key, this.itemCount = 4});

  @override
  Widget build(BuildContext context) {
    return SliverPadding(
      padding: const EdgeInsets.all(16),
      sliver: SliverList(
        delegate: SliverChildBuilderDelegate(
          (context, index) => Shimmer.fromColors(
            baseColor: Colors.grey.shade300,
            highlightColor: Colors.grey.shade100,
            child: Container(
              margin: const EdgeInsets.only(bottom: 16),
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(16),
              ),
              child: Row(
                children: [
                  Container(
                    width: 80,
                    height: 80,
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(8),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Container(height: 14, width: double.infinity, color: Colors.white),
                        const SizedBox(height: 8),
                        Container(height: 14, width: 100, color: Colors.white),
                        const SizedBox(height: 12),
                        Container(height: 16, width: 80, color: Colors.white),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
          childCount: itemCount,
        ),
      ),
    );
  }
}

class ListShimmerLoading extends StatelessWidget {
  const ListShimmerLoading({super.key});

  @override
  Widget build(BuildContext context) {
    return Shimmer.fromColors(
      baseColor: Colors.grey.shade300,
      highlightColor: Colors.grey.shade100,
      child: ListView.builder(
        padding: const EdgeInsets.all(16),
        itemCount: 5,
        itemBuilder: (context, index) {
          return Container(
            margin: const EdgeInsets.only(bottom: 16),
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(12),
            ),
            child: Row(
              children: [
                Container(
                  width: 80,
                  height: 80,
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(8),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Container(height: 14, width: double.infinity, color: Colors.white),
                      const SizedBox(height: 8),
                      Container(height: 14, width: 100, color: Colors.white),
                      const SizedBox(height: 12),
                      Container(height: 16, width: 80, color: Colors.white),
                    ],
                  ),
                )
              ],
            ),
          );
        },
      ),
    );
  }
}
