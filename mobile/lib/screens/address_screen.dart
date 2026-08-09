import 'package:go_router/go_router.dart';
import 'package:flutter/material.dart';
import 'package:dio/dio.dart';
import '../services/api_client.dart';

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
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Text('Xác nhận xóa', style: TextStyle(fontWeight: FontWeight.bold)),
        content: const Text('Bạn có chắc muốn xóa địa chỉ này không?'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Hủy')),
          ElevatedButton(
            onPressed: () => Navigator.pop(ctx, true),
            style: ElevatedButton.styleFrom(backgroundColor: Colors.red, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8))),
            child: const Text('Xóa', style: TextStyle(color: Colors.white)),
          ),
        ],
      ),
    );
    if (confirm != true) return;

    try {
      await ApiClient().dio.delete('/profile/addresses/$id');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Đã xóa địa chỉ'), backgroundColor: Colors.green));
        fetchAddresses();
      }
    } on DioException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.response?.data?['message'] ?? 'Xóa thất bại'), backgroundColor: Colors.red));
    }
  }

  Future<void> setDefaultAddress(int id) async {
    try {
      await ApiClient().dio.put('/profile/addresses/$id/default');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Đã đặt làm địa chỉ mặc định'), backgroundColor: Colors.green));
        fetchAddresses();
      }
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text(
              'Không thể đặt địa chỉ mặc định. Vui lòng thử lại.',
            ),
            backgroundColor: Colors.red,
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
    List<dynamic> districts = [];
    List<dynamic> wards = [];
    String? selectedProvCode = existing?['province_code'];
    String? selectedProvName = existing?['province'];
    String? selectedDistCode = existing?['district_code'];
    String? selectedDistName = existing?['district'];
    String? selectedWardCode = existing?['ward_code'];
    String? selectedWardName = existing?['ward'];
    bool isSaving = false;
    final isEditing = existing != null;

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(24))),
      builder: (context) {
        return StatefulBuilder(
          builder: (BuildContext ctx, StateSetter setModal) {
            // Load provinces on first build
            if (provinces.isEmpty) {
              ApiClient().dio.get('/location/provinces').then((res) {
                if (res.statusCode == 200) {
                  setModal(() => provinces = res.data['data'] is List ? res.data['data'] : []);
                }
              }).catchError((_) {});
            }

            // Load wards when province selected
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
              padding: EdgeInsets.only(bottom: MediaQuery.of(ctx).viewInsets.bottom, left: 20, right: 20, top: 20),
              child: SingleChildScrollView(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // Handle
                    Center(child: Container(width: 40, height: 4, decoration: BoxDecoration(color: Colors.grey.shade300, borderRadius: BorderRadius.circular(2)))),
                    const SizedBox(height: 16),
                    Text(isEditing ? 'Chỉnh Sửa Địa Chỉ' : 'Thêm Địa Chỉ Mới',
                        style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                    const SizedBox(height: 20),
                    _inputField(controller: nameCtrl, label: 'Tên người nhận *', icon: Icons.person_outline),
                    const SizedBox(height: 12),
                    _inputField(controller: phoneCtrl, label: 'Số điện thoại *', icon: Icons.phone_outlined, type: TextInputType.phone),
                    const SizedBox(height: 12),
                    _dropdown(
                      label: 'Tỉnh/Thành phố',
                      value: selectedProvCode,
                      items: provinces.map((p) => DropdownMenuItem(value: p['id'].toString(), child: Text(p['name'].toString(), overflow: TextOverflow.ellipsis))).toList(),
                      onChanged: onProvChanged,
                    ),
                    const SizedBox(height: 12),
                    _dropdown(
                      label: 'Phường/Xã',
                      value: selectedWardCode,
                      items: wards.map((w) => DropdownMenuItem(value: w['id'].toString(), child: Text(w['name'].toString(), overflow: TextOverflow.ellipsis))).toList(),
                      onChanged: wards.isEmpty ? null : onWardChanged,
                    ),
                    const SizedBox(height: 12),
                    _inputField(controller: addressCtrl, label: 'Số nhà, Tên đường *', icon: Icons.home_outlined),
                    const SizedBox(height: 24),
                    SizedBox(
                      width: double.infinity,
                      child: ElevatedButton(
                        style: ElevatedButton.styleFrom(
                          backgroundColor: const Color(0xFFE63B6F),
                          padding: const EdgeInsets.symmetric(vertical: 16),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                          elevation: 0,
                        ),
                        onPressed: isSaving ? null : () async {
                          final phone = phoneCtrl.text.trim();
                          final phoneRegExp = RegExp(r'^(0|\+84|84)[35789][0-9]{8}$');

                          if (nameCtrl.text.trim().isEmpty || addressCtrl.text.trim().isEmpty || phone.isEmpty) {
                            ScaffoldMessenger.of(ctx).showSnackBar(const SnackBar(content: Text('Vui lòng điền đủ thông tin bắt buộc (*)')));
                            return;
                          }

                          if (!phoneRegExp.hasMatch(phone)) {
                            ScaffoldMessenger.of(ctx).showSnackBar(const SnackBar(content: Text('Số điện thoại không hợp lệ (ví dụ: 0912345678)')));
                            return;
                          }

                          setModal(() => isSaving = true);
                          final navigator = Navigator.of(ctx);
                          final messenger = ScaffoldMessenger.of(ctx);
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
                              await ApiClient().dio.put('/profile/addresses/${existing['address_id'] ?? existing['id']}', data: payload);
                            } else {
                              await ApiClient().dio.post('/profile/addresses', data: payload);
                            }
                            context.pop();
                            messenger.showSnackBar(SnackBar(
                              content: Text(isEditing ? 'Cập nhật địa chỉ thành công!' : 'Thêm địa chỉ thành công!'),
                              backgroundColor: Colors.green,
                            ));
                            fetchAddresses();
                          } on DioException catch (e) {
                            setModal(() => isSaving = false);
                            messenger.showSnackBar(SnackBar(content: Text(e.response?.data?['message'] ?? 'Lưu thất bại'), backgroundColor: Colors.red));
                          }
                        },
                        child: isSaving
                          ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                          : Text(isEditing ? 'Cập nhật địa chỉ' : 'Lưu địa chỉ', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 16)),
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

  Widget _inputField({required TextEditingController controller, required String label, required IconData icon, TextInputType type = TextInputType.text}) {
    return Container(
      decoration: BoxDecoration(color: const Color(0xFFF8FAFC), borderRadius: BorderRadius.circular(12), border: Border.all(color: const Color(0xFFE2E8F0))),
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 4),
      child: Row(
        children: [
          Icon(icon, size: 18, color: const Color(0xFF64748B)),
          const SizedBox(width: 10),
          Expanded(child: TextField(
            controller: controller,
            keyboardType: type,
            style: const TextStyle(fontSize: 14, color: Color(0xFF0F172A)),
            decoration: InputDecoration(labelText: label, labelStyle: const TextStyle(fontSize: 12, color: Color(0xFF64748B)), border: InputBorder.none, isDense: true),
          )),
        ],
      ),
    );
  }

  Widget _dropdown({required String label, required String? value, required List<DropdownMenuItem<String>> items, void Function(dynamic)? onChanged}) {
    return Container(
      decoration: BoxDecoration(color: const Color(0xFFF8FAFC), borderRadius: BorderRadius.circular(12), border: Border.all(color: const Color(0xFFE2E8F0))),
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 4),
      child: DropdownButtonFormField<String>(
        decoration: InputDecoration(labelText: label, labelStyle: const TextStyle(fontSize: 12, color: Color(0xFF64748B)), border: InputBorder.none, isDense: true),
        value: value,
        isExpanded: true,
        items: items,
        onChanged: onChanged,
        style: const TextStyle(fontSize: 14, color: Color(0xFF0F172A)),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        title: const Text('Sổ địa chỉ', style: TextStyle(color: Color(0xFF0F172A), fontWeight: FontWeight.bold, fontSize: 18)),
        backgroundColor: Colors.white,
        centerTitle: true,
        elevation: 0,
        iconTheme: const IconThemeData(color: Color(0xFFE63B6F)),
      ),
      body: isLoading
          ? const Center(child: CircularProgressIndicator(color: Color(0xFFE63B6F)))
          : addresses.isEmpty
              ? Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      const Icon(Icons.location_off_outlined, size: 64, color: Color(0xFF64748B)),
                      const SizedBox(height: 16),
                      const Text('Chưa có địa chỉ nào', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: Color(0xFF64748B))),
                      const SizedBox(height: 8),
                      const Text('Thêm địa chỉ để tiện mua sắm hơn', style: TextStyle(color: Color(0xFF64748B))),
                    ],
                  ),
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
                        borderRadius: BorderRadius.circular(16),
                        border: isDefault ? Border.all(color: const Color(0xFFE63B6F), width: 1.5) : null,
                        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.04), blurRadius: 10)],
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
                                      child: Text(addr['recipient_name'] ?? '', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15, color: Color(0xFF0F172A)), overflow: TextOverflow.ellipsis),
                                    ),
                                    if (isDefault) ...[
                                      const SizedBox(width: 8),
                                      Container(
                                        padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                        decoration: BoxDecoration(color: const Color(0xFFE63B6F).withValues(alpha: 0.1), borderRadius: BorderRadius.circular(4)),
                                        child: const Text('Mặc định', style: TextStyle(color: Color(0xFFE63B6F), fontSize: 10, fontWeight: FontWeight.bold)),
                                      ),
                                    ],
                                  ],
                                ),
                              ),
                              Row(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  GestureDetector(
                                    onTap: () => _showAddAddressModal(existing: addr),
                                    child: const Padding(
                                      padding: EdgeInsets.all(10),
                                      child: Icon(Icons.edit_outlined, color: Color(0xFFE63B6F), size: 20),
                                    ),
                                  ),
                                  GestureDetector(
                                    onTap: () => deleteAddress(addrId),
                                    child: const Padding(
                                      padding: EdgeInsets.all(10),
                                      child: Icon(Icons.delete_outline, color: Colors.red, size: 20),
                                    ),
                                  ),
                                ],
                              ),
                            ],
                          ),
                          const SizedBox(height: 6),
                          Text(addr['phone'] ?? '', style: const TextStyle(color: Color(0xFF64748B), fontSize: 13)),
                          const SizedBox(height: 6),
                          Text(
                            '${addr['address_line']}, ${addr['ward']}, ${addr['province']}',
                            style: const TextStyle(color: Color(0xFF334155), height: 1.5, fontSize: 13),
                          ),
                          if (!isDefault) ...[
                            const SizedBox(height: 10),
                            GestureDetector(
                              onTap: () => setDefaultAddress(addrId),
                              child: const Text('Đặt làm mặc định', style: TextStyle(color: Color(0xFFE63B6F), fontSize: 12, fontWeight: FontWeight.w600)),
                            ),
                          ],
                          if (widget.isSelecting) ...[
                            const SizedBox(height: 10),
                            SizedBox(
                              width: double.infinity,
                              child: OutlinedButton(
                                onPressed: () => context.pop(false),
                                style: OutlinedButton.styleFrom(side: const BorderSide(color: Color(0xFFE63B6F)), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8))),
                                child: const Text('Chọn địa chỉ này', style: TextStyle(color: Color(0xFFE63B6F))),
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
          padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
          child: ElevatedButton.icon(
            onPressed: () => _showAddAddressModal(),
            icon: const Icon(Icons.add, color: Colors.white),
            label: const Text('Thêm địa chỉ mới', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 16)),
            style: ElevatedButton.styleFrom(
              backgroundColor: const Color(0xFFE63B6F),
              padding: const EdgeInsets.symmetric(vertical: 16),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              elevation: 0,
            ),
          ),
        ),
      ),
    );
  }
}
