import Flutter
import UIKit
import UserNotifications
import firebase_messaging
import AppTrackingTransparency

@main
@objc class AppDelegate: FlutterAppDelegate, FlutterImplicitEngineDelegate {
  private var pushRegistrationError: String?
  private var requestActionChannel: FlutterMethodChannel?
  private var pendingRequestAction: [String: String]?
  private var trackingResults: [FlutterResult] = []
  private var trackingRequestInFlight = false
  private var trackingActiveObserver: NSObjectProtocol?

  private func requestTrackingWhenActive() {
    guard !trackingResults.isEmpty, !trackingRequestInFlight else { return }
    guard UIApplication.shared.applicationState == .active else {
      if trackingActiveObserver == nil {
        trackingActiveObserver = NotificationCenter.default.addObserver(
          forName: UIApplication.didBecomeActiveNotification, object: nil, queue: .main
        ) { [weak self] _ in self?.requestTrackingWhenActive() }
      }
      return
    }
    if let observer = trackingActiveObserver {
      NotificationCenter.default.removeObserver(observer)
      trackingActiveObserver = nil
    }
    if ATTrackingManager.trackingAuthorizationStatus != .notDetermined {
      finishTracking(ATTrackingManager.trackingAuthorizationStatus == .authorized)
      return
    }
    trackingRequestInFlight = true
    ATTrackingManager.requestTrackingAuthorization { [weak self] status in
      DispatchQueue.main.async {
        self?.trackingRequestInFlight = false
        self?.finishTracking(status == .authorized)
      }
    }
  }

  private func finishTracking(_ authorized: Bool) {
    let callbacks = trackingResults
    trackingResults.removeAll()
    callbacks.forEach { $0(authorized) }
  }

  override func application(
    _ application: UIApplication,
    didFailToRegisterForRemoteNotificationsWithError error: Error
  ) {
    pushRegistrationError = error.localizedDescription
    super.application(application, didFailToRegisterForRemoteNotificationsWithError: error)
  }

  override func application(
    _ application: UIApplication,
    didFinishLaunchingWithOptions launchOptions: [UIApplication.LaunchOptionsKey: Any]?
  ) -> Bool {
    // Firebase Messaging 16.7 handles late plugin registration with UIScene.
    // Its shared notification delegate must be installed before launch returns.
    UNUserNotificationCenter.current().delegate = self
    FLTFirebaseMessagingPlugin.configureNotificationCenterDelegate()
    let accept = UNNotificationAction(identifier: "TOS_ACCEPT_PLAYER_LINK", title: "Accetta", options: [.foreground, .authenticationRequired])
    let reject = UNNotificationAction(identifier: "TOS_REJECT_PLAYER_LINK", title: "Rifiuta", options: [.foreground, .authenticationRequired, .destructive])
    let category = UNNotificationCategory(identifier: "TOS_PLAYER_LINK", actions: [accept, reject], intentIdentifiers: [], options: [])
    UNUserNotificationCenter.current().getNotificationCategories { categories in
      UNUserNotificationCenter.current().setNotificationCategories(categories.union([category]))
    }
    return super.application(application, didFinishLaunchingWithOptions: launchOptions)
  }

  func didInitializeImplicitFlutterEngine(_ engineBridge: FlutterImplicitEngineBridge) {
    GeneratedPluginRegistrant.register(with: engineBridge.pluginRegistry)
    let actionRegistrar = engineBridge.pluginRegistry.registrar(forPlugin: "TosRequestActions")!
    requestActionChannel = FlutterMethodChannel(name: "tos/request_actions", binaryMessenger: actionRegistrar.messenger())
    requestActionChannel?.setMethodCallHandler { [weak self] call, result in
      guard call.method == "take" else { result(FlutterMethodNotImplemented); return }
      result(self?.pendingRequestAction)
      self?.pendingRequestAction = nil
    }
    let trackingRegistrar = engineBridge.pluginRegistry.registrar(forPlugin: "TosTracking")!
    let trackingChannel = FlutterMethodChannel(name: "tos/tracking", binaryMessenger: trackingRegistrar.messenger())
    trackingChannel.setMethodCallHandler { [weak self] call, result in
      guard call.method == "requestAuthorization" else {
        result(FlutterMethodNotImplemented)
        return
      }
      guard let self = self else {
        result(false)
        return
      }
      self.trackingResults.append(result)
      self.requestTrackingWhenActive()
    }
    let pushRegistrar = engineBridge.pluginRegistry.registrar(forPlugin: "TosPushRegistration")!
    let pushChannel = FlutterMethodChannel(name: "tos/push_registration", binaryMessenger: pushRegistrar.messenger())
    pushChannel.setMethodCallHandler { [weak self] call, result in
      switch call.method {
      case "register":
        // Also request registration on retries after network/APNs failures.
        // Firebase's UIScene setup handles token mapping and notification events.
        self?.pushRegistrationError = nil
        UIApplication.shared.registerForRemoteNotifications()
        result(nil)
      case "registrationError":
        result(self?.pushRegistrationError)
      default:
        result(FlutterMethodNotImplemented)
      }
    }
    let registrar = engineBridge.pluginRegistry.registrar(forPlugin: "TosAppVersion")!
    let channel = FlutterMethodChannel(name: "tos/app_version", binaryMessenger: registrar.messenger())
    channel.setMethodCallHandler { call, result in
      if call.method == "buildNumber" {
        result(Int(Bundle.main.object(forInfoDictionaryKey: "CFBundleVersion") as? String ?? "") ?? 0)
      } else {
        result(FlutterMethodNotImplemented)
      }
    }
  }

  override func userNotificationCenter(_ center: UNUserNotificationCenter, willPresent notification: UNNotification, withCompletionHandler completionHandler: @escaping (UNNotificationPresentationOptions) -> Void) {
    if notification.request.content.categoryIdentifier == "TOS_PLAYER_LINK" {
      completionHandler([.banner, .list, .sound]); return
    }
    super.userNotificationCenter(center, willPresent: notification, withCompletionHandler: completionHandler)
  }

  override func userNotificationCenter(_ center: UNUserNotificationCenter, didReceive response: UNNotificationResponse, withCompletionHandler completionHandler: @escaping () -> Void) {
    if ["TOS_ACCEPT_PLAYER_LINK", "TOS_REJECT_PLAYER_LINK"].contains(response.actionIdentifier) {
      let data = response.notification.request.content.userInfo
      if let id = data["request_id"] as? String, let number = Int(id), number > 0 {
        pendingRequestAction = ["request_id": id, "action": response.actionIdentifier == "TOS_ACCEPT_PLAYER_LINK" ? "accept" : "reject", "recipient_id": data["recipient_id"] as? String ?? ""]
        requestActionChannel?.invokeMethod("pending", arguments: nil)
      }
      completionHandler(); return
    }
    super.userNotificationCenter(center, didReceive: response, withCompletionHandler: completionHandler)
  }
}
