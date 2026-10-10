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
    shape: RoundedRectangleBorder(borderRadius: BorderRadius.all(Radius.circular(16)), side: BorderSide(color: Color(0xffdbe3f0))),
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
