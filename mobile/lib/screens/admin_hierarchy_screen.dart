import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../models/organization.dart';
import '../services/admin_service.dart';
import '../widgets/async_view.dart';

/// Browse the administrative hierarchy (ministry -> ... -> branch) and create
/// new nodes beneath any level the signed-in user is allowed to manage.
class AdminHierarchyScreen extends StatefulWidget {
  const AdminHierarchyScreen({super.key});

  @override
  State<AdminHierarchyScreen> createState() => _AdminHierarchyScreenState();
}

class _AdminHierarchyScreenState extends State<AdminHierarchyScreen> {
  late Future<List<Organization>> _future;

  @override
  void initState() {
    super.initState();
    _future = context.read<AdminService>().tree();
  }

  void _reload() {
    setState(() => _future = context.read<AdminService>().tree());
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('الهيكل الإداري')),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => _showAddDialog(null, null),
        icon: const Icon(Icons.add),
        label: const Text('إضافة جهة'),
      ),
      body: AsyncView<List<Organization>>(
        future: _future,
        onRetry: _reload,
        builder: (context, roots) {
          if (roots.isEmpty) {
            return const Center(child: Text('لا توجد جهات بعد.'));
          }
          return ListView(
            padding: const EdgeInsets.only(bottom: 96),
            children: roots.map((o) => _OrgNode(node: o, onChange: _reload)).toList(),
          );
        },
      ),
    );
  }

  Future<void> _showAddDialog(Organization? parent, String? initialType) async {
    // Families: the parent determines the allowed child type. When the user
    // adds from the FAB we first ask which parent to create under.
    List<Organization> candidates = const [];
    if (parent == null) {
      candidates = await _flatten(context.read<AdminService>());
    }

    if (!mounted) return;
    await showDialog<void>(
      context: context,
      builder: (_) => _AddOrgDialog(
        service: context.read<AdminService>(),
        parent: parent,
        rootCandidates: candidates,
        onCreated: _reload,
      ),
    );
  }

  Future<List<Organization>> _flatten(AdminService service) async {
    final tree = await service.tree();
    final out = <Organization>[];
    void walk(List<Organization> nodes) {
      for (final n in nodes) {
        if (n.childType != null) out.add(n);
        walk(n.children);
      }
    }

    walk(tree);
    return out;
  }
}

class _OrgNode extends StatelessWidget {
  const _OrgNode({required this.node, required this.onChange});

  final Organization node;
  final VoidCallback onChange;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Padding(
      padding: EdgeInsets.only(left: node.level * 12.0),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Card(
            child: ListTile(
              leading: Icon(_iconFor(node.type), color: scheme.primary),
              title: Text(node.name),
              subtitle: Text('${node.typeLabel} • ${node.code}'),
              trailing: node.childType != null
                  ? IconButton(
                      tooltip: 'إضافة ${Organization.typeLabels[node.childType]}',
                      icon: const Icon(Icons.add_circle_outline),
                      onPressed: () => _addChild(context),
                    )
                  : null,
            ),
          ),
          ...node.children.map((c) => _OrgNode(node: c, onChange: onChange)),
        ],
      ),
    );
  }

  Future<void> _addChild(BuildContext context) async {
    await showDialog<void>(
      context: context,
      builder: (_) => _AddOrgDialog(
        service: context.read<AdminService>(),
        parent: node,
        rootCandidates: const [],
        onCreated: onChange,
      ),
    );
  }

  IconData _iconFor(String type) => switch (type) {
        'ministry' => Icons.account_balance,
        'governorate' => Icons.map_outlined,
        'directorate' => Icons.apartment_outlined,
        'school' => Icons.school_outlined,
        'branch' => Icons.storefront_outlined,
        _ => Icons.account_tree_outlined,
      };
}

class _AddOrgDialog extends StatefulWidget {
  const _AddOrgDialog({
    required this.service,
    required this.parent,
    required this.rootCandidates,
    required this.onCreated,
  });

  final AdminService service;
  final Organization? parent;
  final List<Organization> rootCandidates;
  final VoidCallback onCreated;

  @override
  State<_AddOrgDialog> createState() => _AddOrgDialogState();
}

class _AddOrgDialogState extends State<_AddOrgDialog> {
  final _name = TextEditingController();
  final _code = TextEditingController();
  Organization? _parent;
  bool _busy = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _parent = widget.parent;
  }

  @override
  void dispose() {
    _name.dispose();
    _code.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final childType = _parent?.childType;

    return AlertDialog(
      title: Text('إضافة جهة جديدة'),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (widget.parent == null) ...[
              DropdownButtonFormField<Organization>(
                initialValue: _parent,
                decoration: const InputDecoration(labelText: 'الجهة الأم'),
                items: widget.rootCandidates
                    .map((o) => DropdownMenuItem(
                          value: o,
                          child: Text('${o.name} (${o.typeLabel})',
                              overflow: TextOverflow.ellipsis),
                        ))
                    .toList(),
                onChanged: (v) => setState(() => _parent = v),
              ),
              const SizedBox(height: 12),
            ] else
              Padding(
                padding: const EdgeInsets.only(bottom: 12),
                child: Text('الأم: ${_parent!.name} (${_parent!.typeLabel})'),
              ),
            if (childType != null)
              Padding(
                padding: const EdgeInsets.only(bottom: 12),
                child: Text('النوع: ${Organization.typeLabels[childType]}'),
              ),
            TextField(
              controller: _name,
              decoration: const InputDecoration(labelText: 'الاسم'),
              textInputAction: TextInputAction.next,
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _code,
              decoration: const InputDecoration(labelText: 'الرمز (فريد)'),
            ),
            if (_error != null) ...[
              const SizedBox(height: 12),
              Text(_error!, style: const TextStyle(color: Colors.red)),
            ],
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: _busy ? null : () => Navigator.of(context).pop(),
          child: const Text('إلغاء'),
        ),
        FilledButton(
          onPressed: _busy || childType == null ? null : _submit,
          child: _busy
              ? const SizedBox(
                  width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
              : const Text('إنشاء'),
        ),
      ],
    );
  }

  Future<void> _submit() async {
    final type = _parent?.childType;
    if (type == null || _parent == null) return;
    if (_name.text.trim().isEmpty || _code.text.trim().isEmpty) {
      setState(() => _error = 'الاسم والرمز مطلوبان.');
      return;
    }

    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await widget.service.createOrganization(
        parentId: _parent!.id,
        type: type,
        name: _name.text.trim(),
        code: _code.text.trim(),
      );
      if (!mounted) return;
      Navigator.of(context).pop();
      widget.onCreated();
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('تم إنشاء ${Organization.typeLabels[type]}.')),
      );
    } catch (e) {
      if (mounted) {
        setState(() {
          _busy = false;
          _error = '$e';
        });
      }
    }
  }
}
