"use client"

import * as React from "react"
import Link from "next/link"
import { Menu, ArrowRight } from "lucide-react"

import { Button } from "@/components/ui/button"
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
  SheetTrigger,
} from "@/components/ui/sheet"
import { LogoWordmark } from "@/components/site/logo"

const NAV_LINKS = [
  { href: "#problem", label: "Problem" },
  { href: "#dealpackets", label: "DealPackets" },
  { href: "#features", label: "Features" },
  { href: "#demo", label: "Demo" },
  { href: "#pricing", label: "Pricing" },
  { href: "#faq", label: "FAQ" },
] as const

export function SiteHeader() {
  const [open, setOpen] = React.useState(false)

  return (
    <header className="sticky top-0 z-50 w-full border-b border-stone-200/80 bg-white/90 backdrop-blur-md">
      <div className="mx-auto flex h-16 w-full max-w-6xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
        <Link
          href="#top"
          className="rounded-md outline-none focus-visible:ring-2 focus-visible:ring-teal-700 focus-visible:ring-offset-2"
          aria-label="TradeSheet Studio — back to top"
        >
          <LogoWordmark />
        </Link>

        <nav aria-label="Primary" className="hidden items-center gap-1 md:flex">
          {NAV_LINKS.map((link) => (
            <Link
              key={link.href}
              href={link.href}
              className="rounded-md px-3 py-2 text-sm font-medium text-stone-600 transition-colors hover:bg-stone-100 hover:text-stone-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-700"
            >
              {link.label}
            </Link>
          ))}
        </nav>

        <div className="flex items-center gap-2">
          <Button
            asChild
            className="hidden bg-teal-700 text-white hover:bg-teal-800 sm:inline-flex"
          >
            <Link href="#pricing">
              Get TradeSheet Studio
              <ArrowRight aria-hidden="true" />
            </Link>
          </Button>

          {/* Mobile navigation */}
          <Sheet open={open} onOpenChange={setOpen}>
            <SheetTrigger asChild>
              <Button
                variant="outline"
                size="icon"
                className="size-11 border-stone-300 md:hidden"
                aria-label="Open navigation menu"
              >
                <Menu className="size-5" />
              </Button>
            </SheetTrigger>
            <SheetContent side="right" className="w-72 border-stone-200">
              <SheetHeader>
                <SheetTitle asChild>
                  <div>
                    <LogoWordmark />
                  </div>
                </SheetTitle>
                <SheetDescription className="sr-only">
                  Site navigation — pick a section to jump to.
                </SheetDescription>
              </SheetHeader>
              <nav aria-label="Mobile" className="flex flex-col gap-1 px-4">
                {NAV_LINKS.map((link) => (
                  <Link
                    key={link.href}
                    href={link.href}
                    onClick={() => setOpen(false)}
                    className="flex min-h-11 items-center rounded-md px-3 py-2.5 text-base font-medium text-stone-700 transition-colors hover:bg-stone-100 hover:text-stone-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-700"
                  >
                    {link.label}
                  </Link>
                ))}
              </nav>
              <div className="mt-auto p-4">
                <Button
                  asChild
                  className="w-full bg-teal-700 text-white hover:bg-teal-800"
                >
                  <Link href="#pricing" onClick={() => setOpen(false)}>
                    Get TradeSheet Studio
                    <ArrowRight aria-hidden="true" />
                  </Link>
                </Button>
              </div>
            </SheetContent>
          </Sheet>
        </div>
      </div>
    </header>
  )
}
