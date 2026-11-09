package it.torneioldschool.tornei_old_school

import io.flutter.embedding.android.FlutterActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.MethodChannel
import android.app.NotificationChannel
import android.app.NotificationManager
import android.os.Build
import android.os.Bundle
import android.content.Intent

class MainActivity : FlutterActivity() {
    private var requestChannel: MethodChannel? = null
    private fun takeRequestAction(): Map<String,String>? {
        val id=intent?.getIntExtra("tos_request_id",0) ?: 0
        val action=intent?.getStringExtra("tos_request_action") ?: return null
        val nonce=intent?.getStringExtra("tos_request_nonce") ?: return null
        val prefs=getSharedPreferences("tos_request_actions",MODE_PRIVATE)
        if(id<=0 || action !in listOf("accept","reject","open") || prefs.getString("nonce_$id",null)!=nonce)return null
        prefs.edit().remove("nonce_$id").apply();intent?.removeExtra("tos_request_action")
        getSystemService(NotificationManager::class.java).cancel("player-link-request",id)
        return mapOf("request_id" to id.toString(),"action" to action,"recipient_id" to (intent?.getStringExtra("tos_request_recipient") ?: ""))
    }
    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent);setIntent(intent)
        requestChannel?.invokeMethod("pending",null)
    }
    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)
        requestChannel=MethodChannel(flutterEngine.dartExecutor.binaryMessenger,"tos/request_actions")
        requestChannel?.setMethodCallHandler { call,result ->
            if(call.method=="take")result.success(takeRequestAction()) else result.notImplemented()
        }
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
