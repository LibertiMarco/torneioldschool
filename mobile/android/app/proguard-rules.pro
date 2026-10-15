# Room creates WorkManager's generated database through reflection at startup.
# Its bundled class-only rule does not retain the no-argument constructor in
# this release toolchain. Keep this specific reflection entry point.
-keep class androidx.work.impl.WorkDatabase_Impl {
    public <init>();
}
