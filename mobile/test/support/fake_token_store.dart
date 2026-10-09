import 'package:school_app/core/storage/token_store.dart';

/// In-memory token store for widget/unit tests, avoiding platform channels.
class FakeTokenStore extends TokenStore {
  FakeTokenStore({this.token, this.userJson});

  String? token;
  String? userJson;

  @override
  Future<String?> readToken() async => token;

  @override
  Future<void> saveToken(String value) async => token = value;

  @override
  Future<String?> readUser() async => userJson;

  @override
  Future<void> saveUser(String json) async => userJson = json;

  @override
  Future<void> clear() async {
    token = null;
    userJson = null;
  }
}
