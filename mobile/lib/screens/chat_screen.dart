import 'dart:math' as math;
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../config/app_config.dart';
import '../config/app_theme.dart';
import '../providers/chat_provider.dart';
import '../utils/format_utils.dart';
import '../widgets/network_image_widget.dart';
import '../widgets/shimmer_loading.dart';

/// Màn hình Chat chuẩn Quốc Tế (ChatGPT / Telegram / Messenger tier).
/// - Thanh nhập tin nhắn Capsule nguyên khối cao cấp (loại bỏ hoàn toàn viền đôi/khung web lỗi thời).
/// - 100% Đồng bộ màu sắc thương hiệu Hồng Magenta / Rose Sport (#E63B6F).
/// - Hiệu ứng 3 chấm nảy sóng động (Bouncing Wave Typing Indicator).
class ChatScreen extends StatefulWidget {
  final Map<String, dynamic>? inquiryProduct;
  const ChatScreen({super.key, this.inquiryProduct});

  @override
  State<ChatScreen> createState() => _ChatScreenState();
}

class _ChatScreenState extends State<ChatScreen> {
  final TextEditingController _msgController = TextEditingController();
  final ScrollController _scrollController = ScrollController();
  final FocusNode _focusNode = FocusNode();
  bool _hasText = false;
  bool _isFocused = false;

  final List<String> _quickSuggestions = [
    'Tư vấn chọn size phù hợp',
    'Phí ship & Thời gian giao hàng',
    'Chính sách đổi trả 15 ngày',
    'Kiểm tra đơn hàng của tôi',
  ];

  @override
  void initState() {
    super.initState();
    _msgController.addListener(() {
      final has = _msgController.text.trim().isNotEmpty;
      if (has != _hasText) {
        setState(() => _hasText = has);
      }
    });

    _focusNode.addListener(() {
      if (_focusNode.hasFocus != _isFocused) {
        setState(() => _isFocused = _focusNode.hasFocus);
      }
    });

    WidgetsBinding.instance.addPostFrameCallback((_) {
      final provider = context.read<ChatProvider>();
      if (widget.inquiryProduct != null) {
        provider.setInquiryProduct(widget.inquiryProduct);
      }
      provider.initStaffChat();
    });
  }

  @override
  void dispose() {
    _msgController.dispose();
    _scrollController.dispose();
    _focusNode.dispose();
    super.dispose();
  }

  void _scrollToBottom() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (_scrollController.hasClients) {
        _scrollController.animateTo(
          0.0,
          duration: const Duration(milliseconds: 300),
          curve: Curves.easeOutCubic,
        );
      }
    });
  }

  void _sendMessage([String? customText]) async {
    final text = (customText ?? _msgController.text).trim();
    if (text.isEmpty) return;

    final provider = context.read<ChatProvider>();
    _msgController.clear();
    _scrollToBottom();

    if (provider.mode == ChatMode.staff) {
      await provider.sendStaffMessage(text);
    } else {
      await provider.sendAiMessage(text);
    }
    _scrollToBottom();
  }

  void _sendProductInquiry(Map<String, dynamic> product) {
    final name = product['name']?.toString() ?? 'Sản phẩm';
    final price = product['min_price'] ?? product['price'] ?? 0;
    final formattedPrice = FormatUtils.formatPrice(price);
    final msg = 'Chào shop, tôi muốn được tư vấn về sản phẩm: "$name" (Giá: $formattedPrice).';
    _sendMessage(msg);
    context.read<ChatProvider>().clearInquiryProduct();
  }

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<ChatProvider>();
    final isStaff = provider.mode == ChatMode.staff;
    final inquiryProduct = provider.inquiryProduct;
    final isTyping = provider.isSending;

    final List<dynamic> currentList = isStaff
        ? provider.staffMessages.reversed.toList()
        : provider.aiMessages.reversed.toList();

    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0.5,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_rounded, color: AppColors.textPrimary),
          onPressed: () => context.pop(),
        ),
        title: Row(
          children: [
            Stack(
              children: [
                CircleAvatar(
                  radius: 19,
                  backgroundColor: AppColors.primaryContainer,
                  child: Icon(
                    isStaff ? Icons.support_agent_rounded : Icons.auto_awesome_rounded,
                    color: AppColors.primary,
                    size: 20,
                  ),
                ),
                Positioned(
                  bottom: 0,
                  right: 0,
                  child: Container(
                    width: 10,
                    height: 10,
                    decoration: BoxDecoration(
                      color: const Color(0xFF10B981),
                      shape: BoxShape.circle,
                      border: Border.all(color: Colors.white, width: 2),
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    isStaff ? 'CSKH Ocean Sport' : 'Trợ lý AI Ocean 24/7',
                    style: const TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w800,
                      color: AppColors.textPrimary,
                    ),
                  ),
                  Text(
                    isStaff ? 'Đang trực tuyến • Phản hồi nhanh' : 'Sẵn sàng giải đáp tức thì',
                    style: const TextStyle(
                      fontSize: 11.5,
                      color: Color(0xFF10B981),
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(48),
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
            decoration: const BoxDecoration(
              color: Colors.white,
              border: Border(bottom: BorderSide(color: Color(0xFFE2E8F0))),
            ),
            child: Container(
              padding: const EdgeInsets.all(3),
              decoration: BoxDecoration(
                color: const Color(0xFFF1F5F9),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Row(
                children: [
                  Expanded(
                    child: GestureDetector(
                      onTap: () => provider.setMode(ChatMode.staff),
                      child: AnimatedContainer(
                        duration: const Duration(milliseconds: 180),
                        padding: const EdgeInsets.symmetric(vertical: 7),
                        decoration: BoxDecoration(
                          color: isStaff ? Colors.white : Colors.transparent,
                          borderRadius: BorderRadius.circular(9),
                          boxShadow: isStaff
                              ? [
                                  BoxShadow(
                                    color: Colors.black.withValues(alpha: 0.05),
                                    blurRadius: 4,
                                    offset: const Offset(0, 1),
                                  ),
                                ]
                              : null,
                        ),
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Icon(
                              Icons.headset_mic_rounded,
                              size: 15,
                              color: isStaff ? AppColors.primary : AppColors.textSecondary,
                            ),
                            const SizedBox(width: 6),
                            Text(
                              'Nhân viên CSKH',
                              style: TextStyle(
                                fontSize: 12.5,
                                fontWeight: isStaff ? FontWeight.w800 : FontWeight.w600,
                                color: isStaff ? AppColors.primary : AppColors.textSecondary,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                  Expanded(
                    child: GestureDetector(
                      onTap: () => provider.setMode(ChatMode.ai),
                      child: AnimatedContainer(
                        duration: const Duration(milliseconds: 180),
                        padding: const EdgeInsets.symmetric(vertical: 7),
                        decoration: BoxDecoration(
                          color: !isStaff ? Colors.white : Colors.transparent,
                          borderRadius: BorderRadius.circular(9),
                          boxShadow: !isStaff
                              ? [
                                  BoxShadow(
                                    color: Colors.black.withValues(alpha: 0.05),
                                    blurRadius: 4,
                                    offset: const Offset(0, 1),
                                  ),
                                ]
                              : null,
                        ),
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Icon(
                              Icons.auto_awesome_rounded,
                              size: 15,
                              color: !isStaff ? AppColors.primary : AppColors.textSecondary,
                            ),
                            const SizedBox(width: 6),
                            Text(
                              'Trợ lý AI 24/7',
                              style: TextStyle(
                                fontSize: 12.5,
                                fontWeight: !isStaff ? FontWeight.w800 : FontWeight.w600,
                                color: !isStaff ? AppColors.primary : AppColors.textSecondary,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
      body: Column(
        children: [
          // ── Pinned Inquiry Product Card ──
          if (inquiryProduct != null)
            Container(
              margin: const EdgeInsets.fromLTRB(14, 10, 14, 0),
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: const Color(0xFFFFCDD2)),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.04),
                    blurRadius: 8,
                    offset: const Offset(0, 2),
                  ),
                ],
              ),
              child: Row(
                children: [
                  ClipRRect(
                    borderRadius: BorderRadius.circular(10),
                    child: Container(
                      width: 54,
                      height: 54,
                      color: const Color(0xFFF8FAFC),
                      child: NetworkImageWidget(
                        imageUrl: AppConfig.productImageUrl(inquiryProduct),
                        width: 54,
                        height: 54,
                        fit: BoxFit.contain,
                      ),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          inquiryProduct['name']?.toString() ?? 'Sản phẩm',
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            fontSize: 13,
                            fontWeight: FontWeight.w800,
                            color: AppColors.textPrimary,
                          ),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          FormatUtils.formatPrice(inquiryProduct['min_price'] ?? inquiryProduct['price'] ?? 0),
                          style: const TextStyle(
                            fontSize: 13,
                            fontWeight: FontWeight.w900,
                            color: AppColors.primary,
                          ),
                        ),
                      ],
                    ),
                  ),
                  ElevatedButton(
                    onPressed: () => _sendProductInquiry(inquiryProduct),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppColors.primary,
                      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                      elevation: 0,
                    ),
                    child: const Text(
                      'Gửi tư vấn',
                      style: TextStyle(fontSize: 12, fontWeight: FontWeight.w800, color: Colors.white),
                    ),
                  ),
                  const SizedBox(width: 4),
                  IconButton(
                    icon: const Icon(Icons.close_rounded, size: 18, color: AppColors.textMuted),
                    onPressed: () => provider.clearInquiryProduct(),
                    constraints: const BoxConstraints(),
                    padding: const EdgeInsets.all(4),
                  ),
                ],
              ),
            ),

          // ── Messages Stream ──
          Expanded(
            child: provider.isLoading && currentList.isEmpty
                ? const Padding(
                    padding: EdgeInsets.all(16.0),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        ShimmerBlock(width: 220, height: 52, borderRadius: 16),
                        SizedBox(height: 12),
                        Align(
                          alignment: Alignment.centerRight,
                          child: ShimmerBlock(width: 180, height: 48, borderRadius: 16),
                        ),
                        SizedBox(height: 12),
                        ShimmerBlock(width: 260, height: 60, borderRadius: 16),
                      ],
                    ),
                  )
                : currentList.isEmpty && !isTyping
                    ? Center(
                        child: SingleChildScrollView(
                          padding: const EdgeInsets.all(24),
                          child: Column(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              Container(
                                padding: const EdgeInsets.all(20),
                                decoration: const BoxDecoration(
                                  color: Color(0xFFFFF1F2),
                                  shape: BoxShape.circle,
                                ),
                                child: Icon(
                                  isStaff ? Icons.chat_bubble_outline_rounded : Icons.auto_awesome_rounded,
                                  color: AppColors.primary,
                                  size: 42,
                                ),
                              ),
                              const SizedBox(height: 16),
                              Text(
                                isStaff
                                    ? 'Chào mừng bạn đến với Ocean Sport CSKH!'
                                    : 'Xin chào! Tôi là Trợ lý AI Ocean Sport.',
                                textAlign: TextAlign.center,
                                style: const TextStyle(
                                  fontWeight: FontWeight.w900,
                                  fontSize: 15,
                                  color: AppColors.textPrimary,
                                ),
                              ),
                              const SizedBox(height: 6),
                              Text(
                                isStaff
                                    ? 'Nhân viên tư vấn sẵn sàng giải đáp thắc mắc và hỗ trợ đơn hàng của bạn.'
                                    : 'Tôi có thể gợi ý sản phẩm, chọn size vợt/giày và tra cứu thông tin 24/7.',
                                textAlign: TextAlign.center,
                                style: const TextStyle(
                                  fontSize: 13,
                                  color: AppColors.textSecondary,
                                  height: 1.4,
                                ),
                              ),
                              const SizedBox(height: 20),
                              const Text(
                                'GỢI Ý CÂU HỎI NHANH:',
                                style: TextStyle(
                                  fontSize: 11,
                                  fontWeight: FontWeight.w800,
                                  color: AppColors.textMuted,
                                  letterSpacing: 0.5,
                                ),
                              ),
                              const SizedBox(height: 10),
                              Wrap(
                                spacing: 8,
                                runSpacing: 8,
                                alignment: WrapAlignment.center,
                                children: _quickSuggestions.map((sug) {
                                  return ActionChip(
                                    label: Text(sug),
                                    labelStyle: const TextStyle(
                                      fontSize: 12,
                                      fontWeight: FontWeight.w700,
                                      color: AppColors.textPrimary,
                                    ),
                                    backgroundColor: Colors.white,
                                    side: const BorderSide(color: Color(0xFFE2E8F0)),
                                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
                                    onPressed: () => _sendMessage(sug),
                                  );
                                }).toList(),
                              ),
                            ],
                          ),
                        ),
                      )
                    : ListView.builder(
                        controller: _scrollController,
                        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
                        reverse: true,
                        itemCount: currentList.length + (isTyping ? 1 : 0),
                        itemBuilder: (context, index) {
                          if (isTyping && index == 0) {
                            return _TypingBubble(isStaff: isStaff);
                          }

                          final actualIndex = isTyping ? index - 1 : index;
                          final item = currentList[actualIndex];

                          if (isStaff) {
                            final isMe = item['sender_type'] == 'user';
                            final time = item['created_at'] != null
                                ? item['created_at'].toString().substring(11, 16)
                                : 'Vừa xong';
                            return _buildBubble(
                              text: item['message']?.toString() ?? '',
                              time: time,
                              isMe: isMe,
                              senderName: isMe ? 'Bạn' : 'CSKH Ocean Sport',
                            );
                          } else {
                            final isMe = item['role'] == 'user';
                            final time = item['created_at'] != null
                                ? item['created_at'].toString().substring(11, 16)
                                : 'Vừa xong';
                            return _buildBubble(
                              text: item['text']?.toString() ?? '',
                              time: time,
                              isMe: isMe,
                              senderName: isMe ? 'Bạn' : 'Trợ lý AI Ocean',
                            );
                          }
                        },
                      ),
          ),

          // ── Quick Chips Bar ──
          if (currentList.isNotEmpty)
            SizedBox(
              height: 38,
              child: ListView(
                scrollDirection: Axis.horizontal,
                padding: const EdgeInsets.symmetric(horizontal: 14),
                children: _quickSuggestions.map((sug) {
                  return Padding(
                    padding: const EdgeInsets.only(right: 8),
                    child: ActionChip(
                      label: Text(sug),
                      labelStyle: const TextStyle(fontSize: 11.5, fontWeight: FontWeight.w600, color: AppColors.textPrimary),
                      backgroundColor: Colors.white,
                      side: const BorderSide(color: Color(0xFFE2E8F0)),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                      padding: const EdgeInsets.symmetric(horizontal: 4),
                      onPressed: () => _sendMessage(sug),
                    ),
                  );
                }).toList(),
              ),
            ),

          const SizedBox(height: 6),

          // ── Unified Modern Capsule Input Bar (ChatGPT / Telegram Standard) ──
          Container(
            padding: const EdgeInsets.fromLTRB(14, 8, 14, 12),
            decoration: BoxDecoration(
              color: Colors.white,
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withValues(alpha: 0.04),
                  blurRadius: 10,
                  offset: const Offset(0, -3),
                ),
              ],
            ),
            child: SafeArea(
              top: false,
              child: AnimatedContainer(
                duration: const Duration(milliseconds: 200),
                decoration: BoxDecoration(
                  color: const Color(0xFFF8FAFC),
                  borderRadius: BorderRadius.circular(26),
                  border: Border.all(
                    color: _isFocused ? AppColors.primary : const Color(0xFFE2E8F0),
                    width: _isFocused ? 1.5 : 1.0,
                  ),
                  boxShadow: _isFocused
                      ? [
                          BoxShadow(
                            color: AppColors.primary.withValues(alpha: 0.12),
                            blurRadius: 8,
                            offset: const Offset(0, 2),
                          ),
                        ]
                      : null,
                ),
                padding: const EdgeInsets.fromLTRB(14, 4, 6, 4),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    // Sparkle / Assistant indicator icon
                    Padding(
                      padding: const EdgeInsets.only(bottom: 9, right: 6),
                      child: Icon(
                        isStaff ? Icons.chat_bubble_outline_rounded : Icons.auto_awesome_rounded,
                        size: 18,
                        color: _isFocused ? AppColors.primary : const Color(0xFF94A3B8),
                      ),
                    ),

                    // Clean Text Field without ugly default borders
                    Expanded(
                      child: TextField(
                        focusNode: _focusNode,
                        controller: _msgController,
                        minLines: 1,
                        maxLines: 4,
                        textCapitalization: TextCapitalization.sentences,
                        style: const TextStyle(
                          fontSize: 14.5,
                          color: AppColors.textPrimary,
                          fontWeight: FontWeight.w500,
                        ),
                        decoration: InputDecoration(
                          hintText: isStaff ? 'Nhắn tin cho CSKH...' : 'Hỏi bất kỳ điều gì với AI...',
                          hintStyle: const TextStyle(
                            fontSize: 13.5,
                            color: Color(0xFF94A3B8),
                            fontWeight: FontWeight.w400,
                          ),
                          isDense: true,
                          filled: false,
                          border: InputBorder.none,
                          enabledBorder: InputBorder.none,
                          focusedBorder: InputBorder.none,
                          errorBorder: InputBorder.none,
                          focusedErrorBorder: InputBorder.none,
                          disabledBorder: InputBorder.none,
                          contentPadding: const EdgeInsets.symmetric(horizontal: 4, vertical: 10),
                        ),
                        onSubmitted: (_) => _sendMessage(),
                      ),
                    ),

                    const SizedBox(width: 6),

                    // Modern Send Button Pill
                    GestureDetector(
                      onTap: () => _sendMessage(),
                      child: AnimatedContainer(
                        duration: const Duration(milliseconds: 200),
                        width: 38,
                        height: 38,
                        margin: const EdgeInsets.only(bottom: 2),
                        decoration: BoxDecoration(
                          gradient: _hasText ? AppGradients.primary : null,
                          color: _hasText ? null : const Color(0xFFE2E8F0),
                          shape: BoxShape.circle,
                          boxShadow: _hasText
                              ? [
                                  BoxShadow(
                                    color: AppColors.primary.withValues(alpha: 0.35),
                                    blurRadius: 6,
                                    offset: const Offset(0, 2),
                                  ),
                                ]
                              : null,
                        ),
                        child: Center(
                          child: Icon(
                            Icons.arrow_upward_rounded,
                            color: _hasText ? Colors.white : const Color(0xFF94A3B8),
                            size: 20,
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildBubble({
    required String text,
    required String time,
    required bool isMe,
    required String senderName,
  }) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: Row(
        mainAxisAlignment: isMe ? MainAxisAlignment.end : MainAxisAlignment.start,
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          if (!isMe) ...[
            const CircleAvatar(
              radius: 15,
              backgroundColor: AppColors.primaryContainer,
              child: Icon(
                Icons.sports_tennis_rounded,
                size: 16,
                color: AppColors.primary,
              ),
            ),
            const SizedBox(width: 8),
          ],
          Flexible(
            child: Container(
              constraints: BoxConstraints(
                maxWidth: MediaQuery.of(context).size.width * 0.74,
              ),
              padding: const EdgeInsets.symmetric(horizontal: 15, vertical: 11),
              decoration: BoxDecoration(
                color: isMe ? AppColors.primary : Colors.white,
                borderRadius: BorderRadius.only(
                  topLeft: const Radius.circular(18),
                  topRight: const Radius.circular(18),
                  bottomLeft: isMe ? const Radius.circular(18) : const Radius.circular(4),
                  bottomRight: isMe ? const Radius.circular(4) : const Radius.circular(18),
                ),
                border: isMe ? null : Border.all(color: const Color(0xFFE2E8F0)),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.04),
                    blurRadius: 6,
                    offset: const Offset(0, 2),
                  ),
                ],
              ),
              child: Column(
                crossAxisAlignment: isMe ? CrossAxisAlignment.end : CrossAxisAlignment.start,
                children: [
                  Text(
                    text,
                    style: TextStyle(
                      color: isMe ? Colors.white : const Color(0xFF1E293B),
                      fontSize: 14,
                      height: 1.45,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    time,
                    style: TextStyle(
                      color: isMe ? Colors.white70 : AppColors.textMuted,
                      fontSize: 10,
                      fontWeight: FontWeight.w500,
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
}

/// Widget Hiệu Ứng 3 Chấm Nảy Sóng Động Màu Hồng Thương Hiệu
class _TypingBubble extends StatefulWidget {
  final bool isStaff;
  const _TypingBubble({required this.isStaff});

  @override
  State<_TypingBubble> createState() => _TypingBubbleState();
}

class _TypingBubbleState extends State<_TypingBubble> with SingleTickerProviderStateMixin {
  late AnimationController _controller;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1200),
    )..repeat();
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    const dotColor = AppColors.primary;

    return Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          const CircleAvatar(
            radius: 15,
            backgroundColor: AppColors.primaryContainer,
            child: Icon(
              Icons.sports_tennis_rounded,
              size: 16,
              color: AppColors.primary,
            ),
          ),
          const SizedBox(width: 8),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: const BorderRadius.only(
                topLeft: Radius.circular(18),
                topRight: Radius.circular(18),
                bottomLeft: Radius.circular(4),
                bottomRight: Radius.circular(18),
              ),
              border: Border.all(color: const Color(0xFFE2E8F0)),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withValues(alpha: 0.04),
                  blurRadius: 6,
                  offset: const Offset(0, 2),
                ),
              ],
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                AnimatedBuilder(
                  animation: _controller,
                  builder: (context, child) {
                    return Row(
                      mainAxisSize: MainAxisSize.min,
                      children: List.generate(3, (i) {
                        final double offsetProgress = (_controller.value - (i * 0.18)) % 1.0;
                        final double bounce = math.max(0.0, math.sin(offsetProgress * math.pi));
                        return Container(
                          margin: const EdgeInsets.symmetric(horizontal: 2.5),
                          transform: Matrix4.translationValues(0, -bounce * 6, 0),
                          width: 7.5,
                          height: 7.5,
                          decoration: BoxDecoration(
                            color: dotColor.withValues(alpha: 0.4 + (bounce * 0.6)),
                            shape: BoxShape.circle,
                          ),
                        );
                      }),
                    );
                  },
                ),
                const SizedBox(width: 10),
                Text(
                  widget.isStaff ? 'CSKH đang soạn tin...' : 'AI đang suy nghĩ...',
                  style: TextStyle(
                    fontSize: 11.5,
                    fontWeight: FontWeight.w600,
                    color: Colors.grey.shade500,
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
