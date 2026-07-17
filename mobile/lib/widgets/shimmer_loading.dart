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

class SliverShimmerLoading extends StatelessWidget {
  const SliverShimmerLoading({super.key});

  @override
  Widget build(BuildContext context) {
    return SliverPadding(
      padding: const EdgeInsets.all(0),
      sliver: SliverGrid(
        gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
          crossAxisCount: 2,
          crossAxisSpacing: 16,
          mainAxisSpacing: 16,
          childAspectRatio: 0.65,
        ),
        delegate: SliverChildBuilderDelegate(
          (context, index) {
            return Shimmer.fromColors(
              baseColor: Colors.grey.shade300,
               highlightColor: Colors.grey.shade100,
               child: const ShimmerProductItemTemplate()
            );
          },
          childCount: 4, // 4 items nhấp nháy cho CustomScrollView
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
