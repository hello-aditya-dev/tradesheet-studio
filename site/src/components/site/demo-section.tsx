import { Reveal } from "@/components/site/reveal"
import { SectionHeading } from "@/components/site/section-heading"
import { DealPacketDemo } from "@/components/site/demo/dealpacket-demo"

export function DemoSection() {
  return (
    <section
      id="demo"
      aria-labelledby="demo-heading"
      className="scroll-mt-20 border-b border-stone-200/70 bg-gradient-to-b from-white via-teal-50/40 to-white py-16 md:py-24"
    >
      <div className="mx-auto w-full max-w-6xl px-4 sm:px-6 lg:px-8">
        <SectionHeading
          id="demo-heading"
          eyebrow="Interactive demo"
          title="Walk a DealPacket from catalog to order."
          intro="Five steps, fully simulated in your browser: compose the packet, freeze the snapshot, request a quote, survive a mid-negotiation price change, and land a single idempotent order. Flip the simulation switch in step 4 to see the integrity engine earn its keep."
        />
        <Reveal>
          <DealPacketDemo />
        </Reveal>
      </div>
    </section>
  )
}
