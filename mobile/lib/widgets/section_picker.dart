import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../models/academic.dart';
import '../services/academic_service.dart';

/// A dropdown of class sections (e.g. "الصف الأول - أ"). Loads once and lets
/// the parent screen react to selection.
class SectionPicker extends StatefulWidget {
  const SectionPicker({
    super.key,
    required this.onChanged,
    this.label = 'الشعبة',
  });

  final ValueChanged<ClassSection?> onChanged;
  final String label;

  @override
  State<SectionPicker> createState() => _SectionPickerState();
}

class _SectionPickerState extends State<SectionPicker> {
  late Future<List<_SectionOption>> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<List<_SectionOption>> _load() async {
    final classes = await context.read<AcademicService>().classes();
    return [
      for (final c in classes)
        for (final s in c.sections)
          _SectionOption(section: s, className: c.name),
    ];
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<List<_SectionOption>>(
      future: _future,
      builder: (context, snapshot) {
        final options = snapshot.data ?? const <_SectionOption>[];
        return DropdownButtonFormField<int>(
          isExpanded: true,
          decoration: InputDecoration(
            labelText: widget.label,
            border: const OutlineInputBorder(),
          ),
          items: [
            for (final o in options)
              DropdownMenuItem(
                value: o.section.id,
                child: Text('${o.className} - ${o.section.name}',
                    overflow: TextOverflow.ellipsis),
              ),
          ],
          onChanged: (id) {
            if (id == null) return;
            final match =
                options.where((o) => o.section.id == id).cast<_SectionOption?>();
            widget.onChanged(match.isEmpty ? null : match.first!.section);
          },
        );
      },
    );
  }
}

class _SectionOption {
  _SectionOption({required this.section, required this.className});
  final ClassSection section;
  final String className;
}
