import 'package:flutter/material.dart';

const siteBlue = Color(0xff15293e);
const siteRed = Color(0xffd80000);
const siteBackground = Color(0xfff4f4f4);

ThemeData siteTheme() => ThemeData(
  useMaterial3: true,
  fontFamily: 'Roboto',
  colorScheme: ColorScheme.fromSeed(seedColor: siteBlue).copyWith(
    primary: siteBlue,
    onPrimary: Colors.white,
    secondary: siteRed,
    onSecondary: Colors.white,
    primaryContainer: const Color(0xffe8edf5),
    onPrimaryContainer: siteBlue,
    secondaryContainer: const Color(0xffffe5e5),
    onSecondaryContainer: siteRed,
    surface: Colors.white,
    onSurface: siteBlue,
  ),
  scaffoldBackgroundColor: siteBackground,
  inputDecorationTheme: InputDecorationTheme(
    filled: true,
    fillColor: const Color(0xffeef1f5),
    contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
    border: OutlineInputBorder(borderRadius: BorderRadius.circular(14)),
    enabledBorder: OutlineInputBorder(
      borderRadius: BorderRadius.circular(14),
      borderSide: const BorderSide(color: Color(0xffdbe3eb)),
    ),
    focusedBorder: OutlineInputBorder(
      borderRadius: BorderRadius.circular(14),
      borderSide: const BorderSide(color: siteBlue, width: 1.5),
    ),
  ),
  appBarTheme: const AppBarTheme(
    backgroundColor: siteBlue,
    foregroundColor: Colors.white,
    surfaceTintColor: Colors.transparent,
  ),
  tabBarTheme: const TabBarThemeData(
    labelColor: Colors.white,
    unselectedLabelColor: Color(0xffcfe3ff),
    indicatorColor: siteRed,
    indicatorSize: TabBarIndicatorSize.tab,
  ),
  cardTheme: const CardThemeData(
    color: Colors.white,
    surfaceTintColor: Colors.transparent,
    elevation: 0,
    shape: RoundedRectangleBorder(
      borderRadius: BorderRadius.all(Radius.circular(16)),
      side: BorderSide(color: Color(0xffdbe3f0)),
    ),
  ),
  filledButtonTheme: FilledButtonThemeData(
    style: FilledButton.styleFrom(
      backgroundColor: siteRed,
      foregroundColor: Colors.white,
    ),
  ),
  navigationBarTheme: const NavigationBarThemeData(
    backgroundColor: Colors.white,
    indicatorColor: Color(0xffffe5e5),
  ),
);

ThemeData siteDarkTheme() => ThemeData(
  useMaterial3: true,
  brightness: Brightness.dark,
  fontFamily: 'Roboto',
  colorScheme: const ColorScheme.dark(
    primary: Color(0xffaecae5),
    onPrimary: Color(0xff193249),
    primaryContainer: Color(0xff30465b),
    onPrimaryContainer: Color(0xffdce9f5),
    secondary: Color(0xffffa5a5),
    onSecondary: Color(0xff542424),
    secondaryContainer: Color(0xff503238),
    onSecondaryContainer: Color(0xffffdada),
    surface: Color(0xff252a31),
    onSurface: Color(0xffe8ebef),
    onSurfaceVariant: Color(0xffb8c0cb),
    surfaceContainerLowest: Color(0xff1c2026),
    surfaceContainerLow: Color(0xff252a31),
    surfaceContainer: Color(0xff2c323b),
    surfaceContainerHigh: Color(0xff343c46),
    surfaceContainerHighest: Color(0xff3e4753),
    outline: Color(0xff87919f),
    outlineVariant: Color(0xff434d5a),
    error: Color(0xffffb4ab),
    onError: Color(0xff690005),
    errorContainer: Color(0xff59302f),
    onErrorContainer: Color(0xffffdad6),
  ),
  scaffoldBackgroundColor: const Color(0xff1c2026),
  inputDecorationTheme: InputDecorationTheme(
    filled: true,
    fillColor: const Color(0xff2c323b),
    contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
    border: OutlineInputBorder(borderRadius: BorderRadius.circular(14)),
    enabledBorder: OutlineInputBorder(
      borderRadius: BorderRadius.circular(14),
      borderSide: const BorderSide(color: Color(0xff434d5a)),
    ),
    focusedBorder: OutlineInputBorder(
      borderRadius: BorderRadius.circular(14),
      borderSide: const BorderSide(color: Color(0xffb8c0cb), width: 1.5),
    ),
  ),
  appBarTheme: const AppBarTheme(
    backgroundColor: Color(0xff252a31),
    foregroundColor: Color(0xffe8ebef),
    surfaceTintColor: Colors.transparent,
  ),
  cardTheme: const CardThemeData(
    color: Color(0xff252a31),
    surfaceTintColor: Colors.transparent,
    elevation: 0,
    shape: RoundedRectangleBorder(
      borderRadius: BorderRadius.all(Radius.circular(16)),
      side: BorderSide(color: Color(0xff434d5a)),
    ),
  ),
  navigationBarTheme: const NavigationBarThemeData(
    backgroundColor: Color(0xff252a31),
    indicatorColor: Color(0xff503238),
  ),
  filledButtonTheme: FilledButtonThemeData(
    style: FilledButton.styleFrom(
      backgroundColor: Color(0xffb93842),
      foregroundColor: Colors.white,
    ),
  ),
);
