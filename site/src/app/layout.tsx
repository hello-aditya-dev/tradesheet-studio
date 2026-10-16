import type { Metadata } from "next";
import { Geist, Geist_Mono } from "next/font/google";
import "./globals.css";
import { Toaster } from "@/components/ui/toaster";

const geistSans = Geist({
  variable: "--font-geist-sans",
  subsets: ["latin"],
});

const geistMono = Geist_Mono({
  variable: "--font-geist-mono",
  subsets: ["latin"],
});

const FAVICON =
  "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='8' fill='%230f766e'/%3E%3Crect x='6.5' y='8.5' width='13' height='15' rx='2.5' fill='%23ffffff'/%3E%3Crect x='9.2' y='12' width='7.6' height='1.7' rx='0.85' fill='%230f766e'/%3E%3Crect x='9.2' y='15.5' width='7.6' height='1.2' rx='0.6' fill='%2314b8a6'/%3E%3Crect x='9.2' y='18.3' width='4.8' height='1.2' rx='0.6' fill='%2314b8a6'/%3E%3Cpath d='M13.5 27.2 H23.6' stroke='%23fbbf24' stroke-width='2.4' stroke-linecap='round'/%3E%3Cpath d='M20.4 23.8 L24.4 27.2 L20.4 30.6' fill='none' stroke='%23fbbf24' stroke-width='2.4' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E";

export const metadata: Metadata = {
  title: "TradeSheet Studio — The wholesale catalog that becomes an order.",
  description:
    "A self-hosted WooCommerce plugin that turns your wholesale catalog into DealPackets: versioned, buyer-specific offers where the PDF, CSV order sheet, buyer portal and final order stay anchored to one immutable commercial snapshot.",
  keywords: [
    "WooCommerce",
    "wholesale",
    "B2B quoting",
    "price list PDF",
    "CSV order sheet",
    "buyer portal",
    "WordPress plugin",
  ],
  authors: [{ name: "Aditya", url: "https://dev-aditya.com" }],
  icons: {
    icon: FAVICON,
  },
  openGraph: {
    title: "TradeSheet Studio — The wholesale catalog that becomes an order.",
    description:
      "DealPackets keep the PDF catalog, CSV order sheet, buyer portal and final WooCommerce order anchored to one immutable commercial snapshot.",
    siteName: "TradeSheet Studio",
    type: "website",
  },
  twitter: {
    card: "summary",
    title: "TradeSheet Studio",
    description:
      "The wholesale catalog that becomes an order. Self-hosted, GPL-2.0-or-later, one-time launch price $49.",
  },
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="en" suppressHydrationWarning>
      <body
        className={`${geistSans.variable} ${geistMono.variable} antialiased bg-background text-foreground`}
      >
        {children}
        <Toaster />
      </body>
    </html>
  );
}
