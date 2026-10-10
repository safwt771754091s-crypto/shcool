import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:provider/provider.dart';

import 'core/config/app_config.dart';
import 'core/network/api_client.dart';
import 'core/storage/offline_store.dart';
import 'core/storage/token_store.dart';
import 'core/theme/app_theme.dart';
import 'router/app_router.dart';
import 'services/academic_service.dart';
import 'services/admin_service.dart';
import 'services/ai_service.dart';
import 'services/auth_service.dart';
import 'services/competition_service.dart';
import 'services/finance_service.dart';
import 'services/import_service.dart';
import 'services/notification_service.dart';
import 'services/portal_service.dart';
import 'services/records_service.dart';
import 'services/staff_service.dart';
import 'services/student_service.dart';
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
        Provider<AdminService>(create: (_) => AdminService(api)),
        Provider<AcademicService>(create: (_) => AcademicService(api)),
        Provider<StudentService>(create: (_) => StudentService(api)),
        Provider<StaffService>(create: (_) => StaffService(api)),
        Provider<RecordsService>(create: (_) => RecordsService(api)),
        Provider<FinanceService>(create: (_) => FinanceService(api)),
        Provider<CompetitionService>(create: (_) => CompetitionService(api)),
        Provider<NotificationService>(create: (_) => NotificationService(api)),
        Provider<ImportService>(create: (_) => ImportService(api)),
        Provider<SyncService>(create: (_) => SyncService(api, OfflineStore())),
        Provider<AiService>(create: (_) => AiService(api)),
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
