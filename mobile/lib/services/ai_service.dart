import '../core/network/api_client.dart';
import '../models/ai.dart';

/// AI agents API: available agents, conversations and messages.
class AiService {
  AiService(this._api);

  final ApiClient _api;

  Future<AiOverview> overview() async {
    final data = await _api.get('ai') as Map<String, dynamic>;
    return AiOverview.fromJson((data['data'] as Map).cast<String, dynamic>());
  }

  Future<AiConversation> createConversation(String agent) async {
    final data = await _api.post('ai/conversations', data: {'agent': agent})
        as Map<String, dynamic>;
    return AiConversation.fromJson((data['data'] as Map).cast<String, dynamic>());
  }

  Future<List<AiMessage>> messages(int conversationId) async {
    final data = await _api.get('ai/conversations/$conversationId')
        as Map<String, dynamic>;
    final payload = (data['data'] as Map)['messages'] as List? ?? const [];
    return payload
        .map((e) => AiMessage.fromJson((e as Map).cast<String, dynamic>()))
        .toList();
  }

  Future<AiReply> send(int conversationId, String message) async {
    final data = await _api.post(
      'ai/conversations/$conversationId/messages',
      data: {'message': message},
    ) as Map<String, dynamic>;
    return AiReply.fromJson((data['data'] as Map).cast<String, dynamic>());
  }

  Future<void> deleteConversation(int conversationId) async {
    await _api.delete('ai/conversations/$conversationId');
  }
}
