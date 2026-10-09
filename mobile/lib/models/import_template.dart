/// Bulk-import template descriptors.
library;

class ImportTemplate {
  ImportTemplate({
    required this.type,
    required this.label,
    this.headers = const [],
    this.sample = const {},
  });

  final String type;
  final String label;
  final List<String> headers;
  final Map<String, dynamic> sample;

  factory ImportTemplate.fromJson(Map<String, dynamic> json) => ImportTemplate(
        type: json['type']?.toString() ?? '',
        label: json['label']?.toString() ?? '',
        headers: (json['headers'] as List?)
                ?.map((e) => e.toString())
                .toList() ??
            const [],
        sample: (json['sample'] as Map?)?.cast<String, dynamic>() ?? const {},
      );
}
