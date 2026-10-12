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
    primary: Color(0xffa9c9ff),
    onPrimary: Color(0xff10263c),
    secondary: Color(0xffff7777),
    onSecondary: Color(0xff321010),
    surface: Color(0xff17283a),
    onSurface: Color(0xffedf4fc),
    surfaceContainerLowest: Color(0xff101b28),
    surfaceContainerLow: Color(0xff1c3045),
    surfaceContainer: Color(0xff233a52),
    surfaceContainerHigh: Color(0xff2d455e),
    outline: Color(0xff526b84),
    error: Color(0xffff8c8c),
  ),
  scaffoldBackgroundColor: const Color(0xff101b28),
  appBarTheme: const AppBarTheme(
    backgroundColor: Color(0xff101b28),
    foregroundColor: Color(0xffedf4fc),
    surfaceTintColor: Colors.transparent,
  ),
  cardTheme: const CardThemeData(
    color: Color(0xff1c3045),
    surfaceTintColor: Colors.transparent,
    elevation: 0,
    shape: RoundedRectangleBorder(
      borderRadius: BorderRadius.all(Radius.circular(16)),
      side: BorderSide(color: Color(0xff354d64)),
    ),
  ),
  navigationBarTheme: const NavigationBarThemeData(
    backgroundColor: Color(0xff17283a),
    indicatorColor: Color(0xff365273),
  ),
  filledButtonTheme: FilledButtonThemeData(
    style: FilledButton.styleFrom(
      backgroundColor: Color(0xffd80000),
      foregroundColor: Colors.white,
    ),
  ),
);
