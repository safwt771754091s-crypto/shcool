import 'package:flutter/material.dart';
import 'package:file_picker/file_picker.dart';
import 'package:provider/provider.dart';

import '../models/import_template.dart';
import '../services/import_service.dart';
import '../widgets/async_view.dart';

/// Bulk data migration: shows the available Excel/CSV templates and lets the
/// user pick a file to import for the selected type.
class ImportScreen extends StatefulWidget {
  const ImportScreen({super.key});

  @override
  State<ImportScreen> createState() => _ImportScreenState();
}

class _ImportScreenState extends State<ImportScreen> {
  late Future<List<ImportTemplate>> _templates;
  ImportTemplate? _selected;
  bool _busy = false;
  Map<String, dynamic>? _result;
  String? _error;

  @override
  void initState() {
    super.initState();
    _templates = context.read<ImportService>().templates();
  }

  Future<void> _pickAndImport() async {
    final template = _selected;
    if (template == null) return;
    setState(() {
      _busy = true;
      _error = null;
      _result = null;
    });
    try {
      final files = await FilePicker.pickFiles(
        type: FileType.custom,
        allowedExtensions: const ['xlsx', 'csv', 'txt'],
      );
      if (files.isEmpty) {
        setState(() => _busy = false);
        return;
      }
      final file = files.first;
      final bytes = await file.readAsBytes();
      if (!mounted) return;
      final summary = await context.read<ImportService>().upload(
            type: template.type,
            bytes: bytes,
            filename: file.name,
          );
      setState(() {
        _busy = false;
        _result = summary;
      });
    } catch (e) {
      setState(() {
        _busy = false;
        _error = '$e';
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('ترحيل البيانات (Excel/CSV)')),
      body: AsyncView<List<ImportTemplate>>(
        future: _templates,
        onRetry: () => setState(
            () => _templates = context.read<ImportService>().templates()),
        builder: (context, templates) => ListView(
          padding: const EdgeInsets.all(16),
          children: [
            const Text(
              'اختر نوع البيانات، حمّل القالب، ثم ارفع الملف المعبّأ.',
              style: TextStyle(fontSize: 14),
            ),
            const SizedBox(height: 16),
            DropdownButtonFormField<String>(
              initialValue: _selected?.type,
              isExpanded: true,
              decoration: const InputDecoration(
                  labelText: 'نوع البيانات', border: OutlineInputBorder()),
              items: [
                for (final t in templates)
                  DropdownMenuItem(value: t.type, child: Text(t.label)),
              ],
              onChanged: (v) {
                final match =
                    templates.where((t) => t.type == v).cast<ImportTemplate?>();
                setState(() => _selected = match.isEmpty ? null : match.first);
              },
            ),
            if (_selected != null) ...[
              const SizedBox(height: 16),
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(12),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('الأعمدة المطلوبة:',
                          style: Theme.of(context).textTheme.titleSmall),
                      const SizedBox(height: 6),
                      Wrap(
                        spacing: 6,
                        runSpacing: 6,
                        children: [
                          for (final h in _selected!.headers)
                            Chip(label: Text(h)),
                        ],
                      ),
                    ],
                  ),
                ),
              ),
            ],
            const SizedBox(height: 16),
            FilledButton.icon(
              onPressed: _selected == null || _busy ? null : _pickAndImport,
              icon: _busy
                  ? const SizedBox(
                      width: 18, height: 18, child: CircularProgressIndicator())
                  : const Icon(Icons.upload_file_outlined),
              label: const Text('اختيار ملف واستيراد'),
            ),
            if (_error != null)
              Padding(
                padding: const EdgeInsets.only(top: 12),
                child: Text(_error!,
                    style: TextStyle(color: Theme.of(context).colorScheme.error)),
              ),
            if (_result != null)
              Card(
                color: Theme.of(context).colorScheme.primaryContainer,
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text('اكتمل الاستيراد:',
                          style: TextStyle(fontWeight: FontWeight.bold)),
                      const SizedBox(height: 8),
                      Text('الإجمالي: ${_result!['total'] ?? 0}'),
                      Text('جديد: ${_result!['created'] ?? 0}'),
                      Text('محدّث: ${_result!['updated'] ?? 0}'),
                      Text('فاشل: ${_result!['failed'] ?? 0}'),
                    ],
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}
