import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:provider/provider.dart';

import 'core/config/app_config.dart';
import 'core/network/api_client.dart';
import 'core/storage/offline_store.dart';
import 'core/storage/token_store.dart';
import 'core/theme/app_theme.dart';
import 'router/app_router.dart';
import 'services/auth_service.dart';
import 'services/portal_service.dart';
import 'services/sync_service.dart';
import 'services/teacher_service.dart';
import 'state/auth_provider.dart';

void main() {
  runApp(const SchoolApp());
}

class SchoolApp extends StatelessWidget {
  const SchoolApp({super.key});

  @override
  Widget build(BuildContext context) {
    final tokens = TokenStore();
    final api = ApiClient(tokens);

    return MultiProvider(
      providers: [
        Provider<TokenStore>.value(value: tokens),
        Provider<ApiClient>.value(value: api),
        Provider<PortalService>(create: (_) => PortalService(api)),
        Provider<TeacherService>(create: (_) => TeacherService(api)),
        Provider<SyncService>(create: (_) => SyncService(api, OfflineStore())),
        ChangeNotifierProvider<AuthProvider>(
          create: (_) => AuthProvider(AuthService(api, tokens))..bootstrap(),
        ),
      ],
      child: MaterialApp.router(
        title: AppConfig.appName,
        debugShowCheckedModeBanner: false,
        theme: AppTheme.light(),
        routerConfig: appRouter,
        locale: const Locale('ar'),
        supportedLocales: const [Locale('ar'), Locale('en')],
        localizationsDelegates: const [
          GlobalMaterialLocalizations.delegate,
          GlobalWidgetsLocalizations.delegate,
          GlobalCupertinoLocalizations.delegate,
        ],
      ),
    );
  }
}
