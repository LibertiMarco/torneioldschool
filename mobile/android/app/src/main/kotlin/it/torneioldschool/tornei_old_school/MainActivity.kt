package it.torneioldschool.tornei_old_school

import io.flutter.embedding.android.FlutterActivity
import android.app.NotificationChannel
import android.app.NotificationManager
import android.os.Build
import android.os.Bundle

class MainActivity : FlutterActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val channel = NotificationChannel("tos_updates", "Aggiornamenti Tornei Old School", NotificationManager.IMPORTANCE_HIGH)
            channel.description = "Risultati e risposte ai commenti"
            getSystemService(NotificationManager::class.java).createNotificationChannel(channel)
        }
    }
}
