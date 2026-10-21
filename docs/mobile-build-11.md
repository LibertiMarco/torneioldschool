# Build 11 - preparazione del 21 ottobre 2026

## Android

Bundle firmato generato: `mobile/build/app/outputs/bundle/release/app-release.aab`.
Versione verificata nel manifest generato: 0.1.1, versionCode 11, application ID
`it.torneioldschool.tornei_old_school`. Firma JAR verificata; fingerprint SHA256
uguale al certificato pubblico upload esistente. SHA256 del bundle:
`28333FD122782ACDF4C2966E43E2320F64F3B5AA79F13A7C064D6EF41002AAD3`.
Non ancora caricato su Play Console. Ultima build vista nella console: 10, test
chiuso Alpha. Annunci e monetizzazione mantengono la configurazione esistente.

Il primo tentativo locale si era arrestato per memoria Java insufficiente. La
compilazione riuscita ha usato GRADLE_OPTS temporaneo, senza modificare Gradle nel
repository: `-Dorg.gradle.jvmargs=-Xmx3G -Dorg.gradle.workers.max=2
-Dkotlin.daemon.jvm.options=-Xmx512m`. Log privato locale:
`.mobile-tools/verification/android-build-11.log`.

## iOS

Il conflitto SwiftPM Firebase 12.18.0 / 12.19.0 e corretto nel commit 6456f05
forzando CocoaPods nel pubspec del progetto. Flutter pub get --offline passa.
Nuova esecuzione ios-testflight avviata da master, commit 6456f05:
https://codemagic.io/app/6ad251b3d9cf028b79a6fe65/build/6ad7fbe0454d2506f7aba25b
Questa esecuzione ha superato Firebase ma ha trovato un include non modulare di
GoogleMobileAds_Beta.h nel plugin google_mobile_ads 9.1.0. Nel commit 027e0ce
il Podfile abilita l'include del vendor solo per il target google_mobile_ads.
Il rilancio 6ad7fdad454d2506f7aba2b3 ha mostrato che il solo target plugin non
e sufficiente per l'importazione in Runner. Il commit d93ddbe applica la stessa
compatibilita anche al target Runner e al suo importatore Clang via Swift, senza
disabilitare globalmente gli errori del compilatore. Esito da verificare su Mac.

La policy minima sul server resta invariata. Per le prove sui dispositivi vedere
`mobile-next-release-checks.md`; note per i tester in `mobile-build-11-tester-notes.txt`.
