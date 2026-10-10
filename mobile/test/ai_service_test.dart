import 'package:flutter_test/flutter_test.dart';
import 'package:school_app/core/network/api_client.dart';
import 'package:school_app/services/ai_service.dart';

import 'support/fake_dio.dart';
import 'support/fake_token_store.dart';

void main() {
  test('AiService.overview() parses agents and conversations', () async {
    final adapter = FakeAdapter((_) => jsonResponse({
          'data': {
            'available': true,
            'agents': [
              {'key': 'manager_assistant', 'label': 'مساعد الإدارة', 'description': 'وصف'},
            ],
            'conversations': [
              {'id': 7, 'agent': 'manager_assistant', 'title': 'محادثة', 'last_message_at': '2026-01-01'},
            ],
          },
        }));

    final service = AiService(ApiClient(FakeTokenStore(), dio: fakeDio(adapter)));
    final overview = await service.overview();

    expect(overview.available, isTrue);
    expect(overview.agents.single.key, 'manager_assistant');
    expect(overview.conversations.single.id, 7);
    expect(overview.conversations.single.displayTitle, 'محادثة');
  });

  test('AiService.createConversation() posts the agent and returns it', () async {
    final adapter = FakeAdapter((options) {
      expect(options.method, 'POST');
      expect(options.path, 'ai/conversations');
      expect((options.data as Map)['agent'], 'teacher_assistant');
      return jsonResponse({
        'data': {'id': 3, 'agent': 'teacher_assistant', 'title': null},
      }, statusCode: 201);
    });

    final service = AiService(ApiClient(FakeTokenStore(), dio: fakeDio(adapter)));
    final conversation = await service.createConversation('teacher_assistant');

    expect(conversation.id, 3);
    expect(conversation.agent, 'teacher_assistant');
    expect(conversation.displayTitle, 'محادثة جديدة');
  });

  test('AiService.send() parses the reply and tool usage', () async {
    final adapter = FakeAdapter((_) => jsonResponse({
          'data': {
            'reply': {'id': 9, 'role': 'assistant', 'content': 'لديك 12 طالباً.'},
            'tools_used': ['student_count'],
            'notice': null,
            'available': true,
          },
        }));

    final service = AiService(ApiClient(FakeTokenStore(), dio: fakeDio(adapter)));
    final reply = await service.send(1, 'كم عدد الطلاب؟');

    expect(reply.message.content, 'لديك 12 طالباً.');
    expect(reply.message.isUser, isFalse);
    expect(reply.toolsUsed, ['student_count']);
    expect(reply.available, isTrue);
  });

  test('AiService.messages() parses the history', () async {
    final adapter = FakeAdapter((_) => jsonResponse({
          'data': {
            'conversation': {'id': 1, 'agent': 'manager_assistant'},
            'messages': [
              {'id': 1, 'role': 'user', 'content': 'سؤال'},
              {'id': 2, 'role': 'assistant', 'content': 'جواب'},
            ],
          },
        }));

    final service = AiService(ApiClient(FakeTokenStore(), dio: fakeDio(adapter)));
    final messages = await service.messages(1);

    expect(messages, hasLength(2));
    expect(messages.first.isUser, isTrue);
    expect(messages.last.content, 'جواب');
  });
}
