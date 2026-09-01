import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import '../config/app_theme.dart';
import '../models/open_play_models.dart';
import '../providers/open_play_provider.dart';
import 'court_booking/widgets/open_play_card.dart';

class MyOpenPlaysScreen extends StatefulWidget {
  const MyOpenPlaysScreen({super.key});

  @override
  State<MyOpenPlaysScreen> createState() => _MyOpenPlaysScreenState();
}

class _MyOpenPlaysScreenState extends State<MyOpenPlaysScreen> with SingleTickerProviderStateMixin {
  late TabController _tabController;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<OpenPlayProvider>().fetchMyOpenPlays();
    });
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<OpenPlayProvider>();
    final myPlays = provider.myOpenPlays;

    final rawJoined = myPlays['joined'] as List? ?? [];
    final joinedMatches = rawJoined.map((j) => OpenPlayModel.fromJson(j as Map<String, dynamic>)).toList();

    final rawHosted = myPlays['hosted'] as List? ?? [];
    final hostedMatches = rawHosted.map((h) => OpenPlayModel.fromJson(h as Map<String, dynamic>)).toList();

    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        title: const Text('Trận Đấu Của Tôi', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18)),
        backgroundColor: Colors.white,
        elevation: 0,
        bottom: TabBar(
          controller: _tabController,
          labelColor: AppColors.primary,
          unselectedLabelColor: Colors.grey,
          indicatorColor: AppColors.primary,
          indicatorWeight: 3,
          tabs: [
            Tab(text: 'Đã tham gia (${joinedMatches.length})'),
            Tab(text: 'Tôi làm Host (${hostedMatches.length})'),
          ],
        ),
      ),
      body: provider.isLoading
          ? const Center(child: CircularProgressIndicator())
          : TabBarView(
              controller: _tabController,
              children: [
                // Tab 1: Joined Matches
                _buildMatchList(
                  matches: joinedMatches,
                  emptyText: 'Bạn chưa tham gia trận đấu nào.',
                  emptyIcon: Icons.sports_tennis,
                ),

                // Tab 2: Hosted Matches
                _buildMatchList(
                  matches: hostedMatches,
                  emptyText: 'Bạn chưa tạo trận đấu nào.',
                  emptyIcon: Icons.star_border,
                ),
              ],
            ),
    );
  }

  Widget _buildMatchList({
    required List<OpenPlayModel> matches,
    required String emptyText,
    required IconData emptyIcon,
  }) {
    if (matches.isEmpty) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(32),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(emptyIcon, size: 56, color: Colors.grey.shade400),
              const SizedBox(height: 12),
              Text(
                emptyText,
                style: const TextStyle(fontSize: 14, color: Colors.grey, fontWeight: FontWeight.w600),
              ),
              const SizedBox(height: 16),
              ElevatedButton(
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppColors.primary,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                ),
                onPressed: () => context.push('/open-plays'),
                child: const Text('Tìm Kèo Giao Lưu', style: TextStyle(color: Colors.white)),
              ),
            ],
          ),
        ),
      );
    }

    return ListView.builder(
      padding: const EdgeInsets.all(16),
      itemCount: matches.length,
      itemBuilder: (context, index) {
        final match = matches[index];
        return OpenPlayCard(
          match: match,
          onTap: () => context.push('/open-plays/${match.id}'),
        );
      },
    );
  }
}
