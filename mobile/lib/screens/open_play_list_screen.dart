import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';
import '../config/app_theme.dart';
import '../providers/open_play_provider.dart';
import 'court_booking/widgets/open_play_card.dart';

class OpenPlayListScreen extends StatefulWidget {
  const OpenPlayListScreen({super.key});

  @override
  State<OpenPlayListScreen> createState() => _OpenPlayListScreenState();
}

class _OpenPlayListScreenState extends State<OpenPlayListScreen> {
  final TextEditingController _searchController = TextEditingController();
  DateTime _selectedDate = DateTime.now();
  String _selectedSkill = 'all';
  String _selectedGender = 'all';
  String _selectedMatchType = 'all';
  bool _availableOnly = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final provider = context.read<OpenPlayProvider>();
      provider.subscribeGlobalChannel();
      _loadMatches();
    });
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  void _loadMatches() {
    final dateStr = DateFormat('yyyy-MM-dd').format(_selectedDate);
    context.read<OpenPlayProvider>().fetchMatches(
      date: dateStr,
      skillLevel: _selectedSkill,
      genderRule: _selectedGender,
      matchType: _selectedMatchType,
      availableOnly: _availableOnly,
      search: _searchController.text.trim(),
    );
  }

  Future<void> _pickDate() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: _selectedDate,
      firstDate: DateTime.now().subtract(const Duration(days: 1)),
      lastDate: DateTime.now().add(const Duration(days: 30)),
      builder: (context, child) {
        return Theme(
          data: ThemeData.light().copyWith(
            colorScheme: const ColorScheme.light(
              primary: AppColors.primary,
              onPrimary: Colors.white,
            ),
          ),
          child: child!,
        );
      },
    );
    if (picked != null && picked != _selectedDate) {
      setState(() {
        _selectedDate = picked;
      });
      _loadMatches();
    }
  }

  void _showFilterModal() {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (ctx) {
        String tempSkill = _selectedSkill;
        String tempGender = _selectedGender;
        String tempMatch = _selectedMatchType;
        bool tempAvail = _availableOnly;

        return StatefulBuilder(
          builder: (context, setModalState) {
            return Container(
              padding: const EdgeInsets.all(20),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text(
                        'Bộ Lọc Kèo Giao Lưu',
                        style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
                      ),
                      TextButton(
                        onPressed: () {
                          setModalState(() {
                            tempSkill = 'all';
                            tempGender = 'all';
                            tempMatch = 'all';
                            tempAvail = false;
                          });
                        },
                        child: const Text('Đặt lại'),
                      ),
                    ],
                  ),
                  const SizedBox(height: 10),

                  // Trình độ
                  const Text('Trình độ', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 13)),
                  const SizedBox(height: 6),
                  Wrap(
                    spacing: 8,
                    children: [
                      _buildChoiceChip('Tất cả', 'all', tempSkill, (val) => setModalState(() => tempSkill = val)),
                      _buildChoiceChip('Mới chơi', 'beginner', tempSkill, (val) => setModalState(() => tempSkill = val)),
                      _buildChoiceChip('Trung bình', 'intermediate', tempSkill, (val) => setModalState(() => tempSkill = val)),
                      _buildChoiceChip('Nâng cao', 'advanced', tempSkill, (val) => setModalState(() => tempSkill = val)),
                    ],
                  ),
                  const SizedBox(height: 14),

                  // Giới tính
                  const Text('Quy định giới tính', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 13)),
                  const SizedBox(height: 6),
                  Wrap(
                    spacing: 8,
                    children: [
                      _buildChoiceChip('Tất cả', 'all', tempGender, (val) => setModalState(() => tempGender = val)),
                      _buildChoiceChip('Nam & Nữ', 'any', tempGender, (val) => setModalState(() => tempGender = val)),
                      _buildChoiceChip('Chỉ Nam', 'male_only', tempGender, (val) => setModalState(() => tempGender = val)),
                      _buildChoiceChip('Chỉ Nữ', 'female_only', tempGender, (val) => setModalState(() => tempGender = val)),
                    ],
                  ),
                  const SizedBox(height: 14),

                  // Chỉ còn slot
                  SwitchListTile(
                    contentPadding: EdgeInsets.zero,
                    title: const Text('Chỉ hiện kèo còn slot trống', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w600)),
                    value: tempAvail,
                    activeThumbColor: AppColors.primary,
                    onChanged: (val) => setModalState(() => tempAvail = val),
                  ),
                  const SizedBox(height: 16),

                  // Apply button
                  SizedBox(
                    width: double.infinity,
                    height: 48,
                    child: ElevatedButton(
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppColors.primary,
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                      ),
                      onPressed: () {
                        setState(() {
                          _selectedSkill = tempSkill;
                          _selectedGender = tempGender;
                          _selectedMatchType = tempMatch;
                          _availableOnly = tempAvail;
                        });
                        Navigator.pop(ctx);
                        _loadMatches();
                      },
                      child: const Text('Áp Dụng Bộ Lọc', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
                    ),
                  ),
                  const SizedBox(height: 10),
                ],
              ),
            );
          },
        );
      },
    );
  }

  Widget _buildChoiceChip(String label, String value, String currentVal, ValueChanged<String> onSelected) {
    final isSelected = currentVal == value;
    return ChoiceChip(
      label: Text(label),
      selected: isSelected,
      labelStyle: TextStyle(
        fontSize: 12,
        color: isSelected ? Colors.white : Colors.black87,
        fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
      ),
      selectedColor: AppColors.primary,
      backgroundColor: Colors.grey.shade100,
      onSelected: (_) => onSelected(value),
    );
  }

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<OpenPlayProvider>();

    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        title: const Text(
          'Kèo Giao Lưu (Open Play)',
          style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18),
        ),
        backgroundColor: Colors.white,
        elevation: 0,
        centerTitle: false,
        actions: [
          IconButton(
            icon: const Icon(Icons.bookmark_outline, color: AppColors.primary),
            tooltip: 'Trận của tôi',
            onPressed: () => context.push('/my-open-plays'),
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        backgroundColor: AppColors.primary,
        icon: const Icon(Icons.add, color: Colors.white),
        label: const Text('Tạo Kèo Mới', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
        onPressed: () => context.push('/create-open-play'),
      ),
      body: Column(
        children: [
          // Search & Filter Header
          Container(
            color: Colors.white,
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
            child: Column(
              children: [
                // Search Input
                TextField(
                  controller: _searchController,
                  onSubmitted: (_) => _loadMatches(),
                  decoration: InputDecoration(
                    hintText: 'Tìm kiếm kèo, sân, host...',
                    hintStyle: const TextStyle(fontSize: 13, color: Colors.grey),
                    prefixIcon: const Icon(Icons.search, size: 20, color: Colors.grey),
                    suffixIcon: _searchController.text.isNotEmpty
                        ? IconButton(
                            icon: const Icon(Icons.clear, size: 18),
                            onPressed: () {
                              _searchController.clear();
                              _loadMatches();
                            },
                          )
                        : null,
                    contentPadding: const EdgeInsets.symmetric(vertical: 0, horizontal: 16),
                    filled: true,
                    fillColor: const Color(0xFFF1F5F9),
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12),
                      borderSide: BorderSide.none,
                    ),
                  ),
                ),
                const SizedBox(height: 10),

                // Date Picker + Filter Button
                Row(
                  children: [
                    Expanded(
                      child: InkWell(
                        onTap: _pickDate,
                        borderRadius: BorderRadius.circular(10),
                        child: Container(
                          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                          decoration: BoxDecoration(
                            color: const Color(0xFFF1F5F9),
                            borderRadius: BorderRadius.circular(10),
                          ),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              const Icon(Icons.calendar_today, size: 14, color: AppColors.primary),
                              const SizedBox(width: 6),
                              Text(
                                DateFormat('dd/MM/yyyy').format(_selectedDate),
                                style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFF1E293B)),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    InkWell(
                      onTap: _showFilterModal,
                      borderRadius: BorderRadius.circular(10),
                      child: Container(
                        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                        decoration: BoxDecoration(
                          color: const Color(0xFFF1F5F9),
                          borderRadius: BorderRadius.circular(10),
                        ),
                        child: const Row(
                          children: [
                            Icon(Icons.tune, size: 16, color: AppColors.primary),
                            SizedBox(width: 4),
                            Text('Bộ lọc', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFF1E293B))),
                          ],
                        ),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),

          // Matches List
          Expanded(
            child: RefreshIndicator(
              onRefresh: () async => _loadMatches(),
              child: provider.isLoading
                  ? const Center(child: CircularProgressIndicator())
                  : provider.matches.isEmpty
                      ? ListView(
                          padding: const EdgeInsets.all(32),
                          children: [
                            const SizedBox(height: 40),
                            Icon(Icons.sports_tennis, size: 64, color: Colors.grey.shade400),
                            const SizedBox(height: 16),
                            const Text(
                              'Không có kèo giao lưu nào',
                              textAlign: TextAlign.center,
                              style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: Colors.black87),
                            ),
                            const SizedBox(height: 6),
                            const Text(
                              'Hãy thử thay đổi ngày hoặc tạo một kèo giao lưu mới từ lịch đặt sân của bạn!',
                              textAlign: TextAlign.center,
                              style: TextStyle(fontSize: 13, color: Colors.grey),
                            ),
                          ],
                        )
                      : ListView.builder(
                          padding: const EdgeInsets.all(16),
                          itemCount: provider.matches.length,
                          itemBuilder: (context, index) {
                            final match = provider.matches[index];
                            return OpenPlayCard(
                              match: match,
                              onTap: () {
                                context.push('/open-plays/${match.id}');
                              },
                            );
                          },
                        ),
            ),
          ),
        ],
      ),
    );
  }
}
