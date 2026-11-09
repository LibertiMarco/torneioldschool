package it.torneioldschool.tornei_old_school

import android.Manifest
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Intent
import android.content.pm.PackageManager
import android.os.Build
import androidx.core.app.NotificationCompat
import com.google.firebase.messaging.RemoteMessage
import io.flutter.plugins.firebase.messaging.FlutterFirebaseMessagingService
import java.util.UUID

class PlayerLinkMessagingService : FlutterFirebaseMessagingService() {
    override fun onMessageReceived(message: RemoteMessage) {
        if (message.data["kind"] != "player_link_request") return super.onMessageReceived(message)
        val id = message.data["request_id"]?.toIntOrNull()?.takeIf { it > 0 } ?: return
        if (Build.VERSION.SDK_INT >= 33 && checkSelfPermission(Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED) return
        val nonce = UUID.randomUUID().toString()
        getSharedPreferences("tos_request_actions", MODE_PRIVATE).edit().putString("nonce_$id", nonce).apply()
        fun intent(action: String): PendingIntent {
            val target = Intent(this, MainActivity::class.java).apply {
                flags = Intent.FLAG_ACTIVITY_CLEAR_TOP or Intent.FLAG_ACTIVITY_SINGLE_TOP
                putExtra("tos_request_id", id); putExtra("tos_request_action", action)
                putExtra("tos_request_nonce", nonce);putExtra("tos_request_recipient",message.data["recipient_id"] ?: "")
            }
            return PendingIntent.getActivity(this, (id.toString()+action).hashCode(), target, PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE)
        }
        val manager = getSystemService(NotificationManager::class.java)
        if (Build.VERSION.SDK_INT >= 26) manager.createNotificationChannel(NotificationChannel("tos_updates","Aggiornamenti Tornei Old School",NotificationManager.IMPORTANCE_HIGH))
        val notification = NotificationCompat.Builder(this,"tos_updates")
            .setSmallIcon(R.drawable.ic_notification).setContentTitle(message.data["title"] ?: "Richiesta associazione")
            .setContentText(message.data["body"] ?: "").setStyle(NotificationCompat.BigTextStyle().bigText(message.data["body"] ?: ""))
            .setContentIntent(intent("open")).setAutoCancel(true).setPriority(NotificationCompat.PRIORITY_HIGH)
            .addAction(0,"Accetta",intent("accept")).addAction(0,"Rifiuta",intent("reject")).build()
        manager.notify("player-link-request",id,notification)
    }
}
