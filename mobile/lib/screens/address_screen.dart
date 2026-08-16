import 'package:go_router/go_router.dart';
import 'package:flutter/material.dart';
import 'package:dio/dio.dart';
import '../config/app_theme.dart';
import '../services/api_client.dart';
import '../widgets/app_empty_state.dart';

class AddressScreen extends StatefulWidget {
  final bool isSelecting;
  const AddressScreen({super.key, this.isSelecting = false});

  @override
  State<AddressScreen> createState() => _AddressScreenState();
}

class _AddressScreenState extends State<AddressScreen> {
  List<dynamic> addresses = [];
  bool isLoading = true;

  @override
  void initState() {
    super.initState();
    fetchAddresses();
  }

  Future<void> fetchAddresses() async {
    if (mounted) setState(() => isLoading = true);
    try {
      final res = await ApiClient().dio.get('/profile/addresses');
      if (mounted) {
        setState(() {
          addresses = res.data['data'] ?? [];
          isLoading = false;
        });
      }
    } catch (_) {
      if (mounted) setState(() => isLoading = false);
    }
  }

  Future<void> deleteAddress(int id) async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Xác nhận xóa', style: TextStyle(fontWeight: FontWeight.w800)),
        content: const Text('Bạn có chắc muốn xóa địa chỉ nhận hàng này không?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Hủy', style: TextStyle(color: AppColors.textMuted)),
          ),
          ElevatedButton(
            onPressed: () => Navigator.pop(ctx, true),
            style: ElevatedButton.styleFrom(
              backgroundColor: AppColors.error,
              foregroundColor: Colors.white,
            ),
            child: const Text('Xóa'),
          ),
        ],
      ),
    );
    if (confirm != true || !mounted) return;

    try {
      await ApiClient().dio.delete('/profile/addresses/$id');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Đã xóa địa chỉ thành công!'), backgroundColor: AppColors.success),
        );
        fetchAddresses();
      }
    } on DioException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(e.response?.data?['message'] ?? 'Xóa thất bại'), backgroundColor: AppColors.error),
        );
      }
    }
  }

  Future<void> setDefaultAddress(int id) async {
    try {
      await ApiClient().dio.put('/profile/addresses/$id/default');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Đã đặt làm địa chỉ mặc định'), backgroundColor: AppColors.success),
        );
        fetchAddresses();
      }
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Không thể đặt địa chỉ mặc định. Vui lòng thử lại.'),
            backgroundColor: AppColors.error,
          ),
        );
      }
    }
  }

  void _showAddAddressModal({Map<String, dynamic>? existing}) {
    final nameCtrl = TextEditingController(text: existing?['recipient_name'] ?? '');
    final phoneCtrl = TextEditingController(text: existing?['phone'] ?? '');
    final addressCtrl = TextEditingController(text: existing?['address_line'] ?? '');

    List<dynamic> provinces = [];
    List<dynamic> wards = [];
    String? selectedProvCode = existing?['province_code'];
    String? selectedProvName = existing?['province'];
    String? selectedWardCode = existing?['ward_code'];
    String? selectedWardName = existing?['ward'];
    bool isSaving = false;
    final isEditing = existing != null;

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (modalCtx) {
        return StatefulBuilder(
          builder: (BuildContext ctx, StateSetter setModal) {
            if (provinces.isEmpty) {
              ApiClient().dio.get('/location/provinces').then((res) {
                if (res.statusCode == 200) {
                  setModal(() => provinces = res.data['data'] is List ? res.data['data'] : []);
                }
              }).catchError((_) {});
            }

            void onProvChanged(dynamic val) {
              final pv = provinces.firstWhere((p) => p['id'].toString() == val.toString(), orElse: () => {});
              setModal(() {
                selectedProvCode = val.toString();
                selectedProvName = pv['name'] ?? val.toString();
                wards = [];
                selectedWardCode = null;
              });
              ApiClient().dio.get('/location/wards/$val').then((res) {
                if (res.statusCode == 200) setModal(() => wards = res.data['data'] ?? []);
              }).catchError((_) {});
            }

            void onWardChanged(dynamic val) {
              final wd = wards.firstWhere((w) => w['id'].toString() == val.toString(), orElse: () => {});
              setModal(() {
                selectedWardCode = val.toString();
                selectedWardName = wd['name'] ?? val.toString();
              });
            }

            return Padding(
              padding: EdgeInsets.only(
                bottom: MediaQuery.of(ctx).viewInsets.bottom,
                left: 20,
                right: 20,
                top: 20,
              ),
              child: SingleChildScrollView(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Center(
                      child: Container(
                        width: 40,
                        height: 4,
                        decoration: BoxDecoration(
                          color: AppColors.surfaceDim,
                          borderRadius: BorderRadius.circular(2),
                        ),
                      ),
                    ),
                    const SizedBox(height: 16),
                    Text(
                      isEditing ? 'Chỉnh Sửa Địa Chỉ' : 'Thêm Địa Chỉ Mới',
                      style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800),
                    ),
                    const SizedBox(height: 20),
                    _inputField(controller: nameCtrl, label: 'Tên người nhận *', icon: Icons.person_outline),
                    const SizedBox(height: 12),
                    _inputField(
                      controller: phoneCtrl,
                      label: 'Số điện thoại *',
                      icon: Icons.phone_outlined,
                      type: TextInputType.phone,
                    ),
                    const SizedBox(height: 12),
                    _dropdown(
                      label: 'Tỉnh/Thành phố',
                      value: selectedProvCode,
                      items: provinces
                          .map((p) => DropdownMenuItem(
                                value: p['id'].toString(),
                                child: Text(p['name'].toString(), overflow: TextOverflow.ellipsis),
                              ))
                          .toList(),
                      onChanged: onProvChanged,
                    ),
                    const SizedBox(height: 12),
                    _dropdown(
                      label: 'Phường/Xã',
                      value: selectedWardCode,
                      items: wards
                          .map((w) => DropdownMenuItem(
                                value: w['id'].toString(),
                                child: Text(w['name'].toString(), overflow: TextOverflow.ellipsis),
                              ))
                          .toList(),
                      onChanged: wards.isEmpty ? null : onWardChanged,
                    ),
                    const SizedBox(height: 12),
                    _inputField(controller: addressCtrl, label: 'Số nhà, Tên đường *', icon: Icons.home_outlined),
                    const SizedBox(height: 24),
                    SizedBox(
                      width: double.infinity,
                      height: 46,
                      child: ElevatedButton(
                        style: ElevatedButton.styleFrom(
                          backgroundColor: AppColors.primary,
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                        ),
                        onPressed: isSaving
                            ? null
                            : () async {
                                final phone = phoneCtrl.text.trim();
                                final phoneRegExp = RegExp(r'^(0|\+84|84)[35789][0-9]{8}$');

                                if (nameCtrl.text.trim().isEmpty || addressCtrl.text.trim().isEmpty || phone.isEmpty) {
                                  ScaffoldMessenger.of(ctx).showSnackBar(
                                    const SnackBar(content: Text('Vui lòng điền đủ thông tin bắt buộc (*)')),
                                  );
                                  return;
                                }

                                if (!phoneRegExp.hasMatch(phone)) {
                                  ScaffoldMessenger.of(ctx).showSnackBar(
                                    const SnackBar(content: Text('Số điện thoại không hợp lệ (ví dụ: 0912345678)')),
                                  );
                                  return;
                                }

                                setModal(() => isSaving = true);
                                try {
                                  final payload = {
                                    'recipient_name': nameCtrl.text.trim(),
                                    'phone': phoneCtrl.text.trim(),
                                    'province': selectedProvName ?? '',
                                    'province_code': selectedProvCode ?? '',
                                    'ward': selectedWardName ?? '',
                                    'ward_code': selectedWardCode ?? '',
                                    'address_line': addressCtrl.text.trim(),
                                    'is_default': false,
                                  };
                                  if (isEditing) {
                                    await ApiClient().dio.put(
                                      '/profile/addresses/${existing['address_id'] ?? existing['id']}',
                                      data: payload,
                                    );
                                  } else {
                                    await ApiClient().dio.post('/profile/addresses', data: payload);
                                  }

                                  if (modalCtx.mounted) {
                                    Navigator.pop(modalCtx);
                                  }

                                  if (mounted) {
                                    ScaffoldMessenger.of(context).showSnackBar(SnackBar(
                                      content: Text(isEditing ? 'Cập nhật địa chỉ thành công!' : 'Thêm địa chỉ thành công!'),
                                      backgroundColor: AppColors.success,
                                    ));
                                    fetchAddresses();
                                  }
                                } on DioException catch (e) {
                                  setModal(() => isSaving = false);
                                  if (ctx.mounted) {
                                    ScaffoldMessenger.of(ctx).showSnackBar(SnackBar(
                                      content: Text(e.response?.data?['message'] ?? 'Lưu thất bại'),
                                      backgroundColor: AppColors.error,
                                    ));
                                  }
                                }
                              },
                        child: isSaving
                            ? const SizedBox(
                                width: 20,
                                height: 20,
                                child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2),
                              )
                            : Text(
                                isEditing ? 'Cập nhật địa chỉ' : 'Lưu địa chỉ',
                                style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 14.5),
                              ),
                      ),
                    ),
                    const SizedBox(height: 24),
                  ],
                ),
              ),
            );
          },
        );
      },
    );
  }

  Widget _inputField({
    required TextEditingController controller,
    required String label,
    required IconData icon,
    TextInputType type = TextInputType.text,
  }) {
    return Container(
      decoration: BoxDecoration(
        color: AppColors.surfaceDim,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: AppColors.border),
      ),
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 4),
      child: Row(
        children: [
          Icon(icon, size: 18, color: AppColors.textMuted),
          const SizedBox(width: 10),
          Expanded(
            child: TextField(
              controller: controller,
              keyboardType: type,
              style: const TextStyle(fontSize: 14, color: AppColors.textPrimary),
              decoration: InputDecoration(
                labelText: label,
                labelStyle: const TextStyle(fontSize: 12, color: AppColors.textMuted),
                border: InputBorder.none,
                enabledBorder: InputBorder.none,
                focusedBorder: InputBorder.none,
                errorBorder: InputBorder.none,
                focusedErrorBorder: InputBorder.none,
                disabledBorder: InputBorder.none,
                isDense: true,
                filled: false,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _dropdown({
    required String label,
    required String? value,
    required List<DropdownMenuItem<String>> items,
    void Function(dynamic)? onChanged,
  }) {
    return Container(
      decoration: BoxDecoration(
        color: AppColors.surfaceDim,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: AppColors.border),
      ),
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 4),
      child: DropdownButtonFormField<String>(
        decoration: InputDecoration(
          labelText: label,
          labelStyle: const TextStyle(fontSize: 12, color: AppColors.textMuted),
          border: InputBorder.none,
          enabledBorder: InputBorder.none,
          focusedBorder: InputBorder.none,
          errorBorder: InputBorder.none,
          focusedErrorBorder: InputBorder.none,
          disabledBorder: InputBorder.none,
          isDense: true,
          filled: false,
        ),
        initialValue: value,
        isExpanded: true,
        items: items,
        onChanged: onChanged,
        style: const TextStyle(fontSize: 14, color: AppColors.textPrimary),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: const Text(
          'Sổ Địa Chỉ',
          style: TextStyle(fontWeight: FontWeight.w900, fontSize: 18),
        ),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_rounded),
          onPressed: () => context.pop(),
        ),
      ),
      body: isLoading
          ? const Center(child: CircularProgressIndicator(color: AppColors.primary))
          : addresses.isEmpty
              ? AppEmptyState(
                  icon: Icons.location_off_outlined,
                  title: 'Chưa có địa chỉ nào',
                  message: 'Thêm địa chỉ nhận hàng để việc đặt hàng nhanh chóng và thuận tiện hơn.',
                  buttonText: 'Thêm địa chỉ',
                  onAction: () => _showAddAddressModal(),
                )
              : ListView.builder(
                  padding: const EdgeInsets.all(16),
                  itemCount: addresses.length,
                  itemBuilder: (context, index) {
                    final addr = addresses[index];
                    final isDefault = addr['is_default'] == 1 || addr['is_default'] == true;
                    final addrId = addr['address_id'] ?? addr['id'];

                    return Container(
                      margin: const EdgeInsets.only(bottom: 12),
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(18),
                        border: isDefault
                            ? Border.all(color: AppColors.primary, width: 1.5)
                            : Border.all(color: AppColors.border),
                        boxShadow: AppShadows.card,
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              Expanded(
                                child: Row(
                                  children: [
                                    Flexible(
                                      child: Text(
                                        addr['recipient_name'] ?? '',
                                        style: const TextStyle(
                                          fontWeight: FontWeight.w800,
                                          fontSize: 15,
                                          color: AppColors.textPrimary,
                                        ),
                                        overflow: TextOverflow.ellipsis,
                                      ),
                                    ),
                                    if (isDefault) ...[
                                      const SizedBox(width: 8),
                                      Container(
                                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                                        decoration: BoxDecoration(
                                          color: AppColors.primaryContainer,
                                          borderRadius: BorderRadius.circular(6),
                                        ),
                                        child: const Text(
                                          'Mặc định',
                                          style: TextStyle(
                                            color: AppColors.primary,
                                            fontSize: 10,
                                            fontWeight: FontWeight.w800,
                                          ),
                                        ),
                                      ),
                                    ],
                                  ],
                                ),
                              ),
                              Row(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  IconButton(
                                    icon: const Icon(Icons.edit_outlined, color: AppColors.primary, size: 20),
                                    onPressed: () => _showAddAddressModal(existing: addr),
                                  ),
                                  IconButton(
                                    icon: const Icon(Icons.delete_outline, color: AppColors.error, size: 20),
                                    onPressed: () => deleteAddress(addrId),
                                  ),
                                ],
                              ),
                            ],
                          ),
                          const SizedBox(height: 6),
                          Text(
                            addr['phone'] ?? '',
                            style: const TextStyle(color: AppColors.textSecondary, fontSize: 13),
                          ),
                          const SizedBox(height: 6),
                          Text(
                            '${addr['address_line']}, ${addr['ward']}, ${addr['province']}',
                            style: const TextStyle(color: AppColors.textPrimary, height: 1.4, fontSize: 13),
                          ),
                          if (!isDefault) ...[
                            const SizedBox(height: 10),
                            GestureDetector(
                              onTap: () => setDefaultAddress(addrId),
                              child: const Text(
                                'Đặt làm mặc định',
                                style: TextStyle(
                                  color: AppColors.primary,
                                  fontSize: 12,
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                            ),
                          ],
                          if (widget.isSelecting) ...[
                            const SizedBox(height: 12),
                            SizedBox(
                              width: double.infinity,
                              child: OutlinedButton(
                                onPressed: () => context.pop(addr),
                                style: OutlinedButton.styleFrom(
                                  side: const BorderSide(color: AppColors.primary),
                                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                                ),
                                child: const Text(
                                  'Chọn địa chỉ này',
                                  style: TextStyle(color: AppColors.primary, fontWeight: FontWeight.w700),
                                ),
                              ),
                            ),
                          ],
                        ],
                      ),
                    );
                  },
                ),
      bottomNavigationBar: SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
          child: SizedBox(
            height: 46,
            child: ElevatedButton.icon(
              onPressed: () => _showAddAddressModal(),
              icon: const Icon(Icons.add_rounded, color: Colors.white, size: 20),
              label: const Text(
                'Thêm địa chỉ mới',
                style: TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 14.5),
              ),
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.primary,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
