import Flutter
import UIKit
import UserNotifications
import firebase_messaging

@main
@objc class AppDelegate: FlutterAppDelegate, FlutterImplicitEngineDelegate {
  private var pushRegistrationError: String?

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
    FLTFirebaseMessagingPlugin.configureNotificationCenterDelegate()
    return super.application(application, didFinishLaunchingWithOptions: launchOptions)
  }

  func didInitializeImplicitFlutterEngine(_ engineBridge: FlutterImplicitEngineBridge) {
    GeneratedPluginRegistrant.register(with: engineBridge.pluginRegistry)
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
}
