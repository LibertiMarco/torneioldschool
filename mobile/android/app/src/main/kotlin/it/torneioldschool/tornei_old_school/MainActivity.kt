package it.torneioldschool.tornei_old_school

import io.flutter.embedding.android.FlutterActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.MethodChannel
import android.app.NotificationChannel
import android.app.NotificationManager
import android.os.Build
import android.os.Bundle

class MainActivity : FlutterActivity() {
    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)
        MethodChannel(flutterEngine.dartExecutor.binaryMessenger, "tos/app_version")
            .setMethodCallHandler { call, result ->
                if (call.method == "buildNumber") {
                    val info = packageManager.getPackageInfo(packageName, 0)
                    val build = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.P) info.longVersionCode else info.versionCode.toLong()
                    result.success(build)
                } else result.notImplemented()
            }
    }
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val channel = NotificationChannel("tos_updates", "Aggiornamenti Tornei Old School", NotificationManager.IMPORTANCE_HIGH)
            channel.description = "Risultati e risposte ai commenti"
            getSystemService(NotificationManager::class.java).createNotificationChannel(channel)
        }
    }
}
