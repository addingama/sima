"use client";

import { useMemo, useState } from "react";

import { PageHeader } from "@/components/sima/page-header";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { filterGlossary, glossaryGroups } from "@/lib/glossary";

export function GlossaryPage() {
  const [query, setQuery] = useState("");
  const groups = useMemo(() => filterGlossary(glossaryGroups, query), [query]);

  return (
    <div className="mx-auto flex w-full max-w-5xl flex-col gap-6">
      <PageHeader
        title="Daftar istilah"
        description="Arti istilah yang dipakai di SIMA, dari dana dan kas sampai peran pengguna."
      />

      <label htmlFor="glossary-search" className="flex max-w-md flex-col gap-2 text-sm">
        <span className="font-medium">Cari istilah</span>
        <Input
          id="glossary-search"
          value={query}
          placeholder="Nama, alias, atau penjelasan"
          onChange={(event) => setQuery(event.target.value)}
        />
      </label>

      {groups.length === 0 ? (
        <p className="text-muted-foreground text-sm">Tidak ada istilah yang cocok.</p>
      ) : (
        <div className="grid items-start gap-6 lg:grid-cols-[13rem_minmax(0,1fr)]">
          <nav aria-label="Kelompok istilah" className="lg:sticky lg:top-4">
            <ul className="flex gap-2 overflow-x-auto lg:flex-col">
              {groups.map((group) => (
                <li key={group.id}>
                  <a href={`#${group.id}`} className="inline-flex rounded-md px-2 py-1 text-sm hover:bg-muted">
                    {group.title}
                  </a>
                </li>
              ))}
            </ul>
          </nav>

          <div className="flex flex-col gap-8">
            {groups.map((group) => (
              <section key={group.id} id={group.id} className="scroll-mt-6">
                <h2 className="mb-3 font-semibold text-lg">{group.title}</h2>
                <ul className="flex flex-col gap-3">
                  {group.terms.map((term) => (
                    <li key={term.id}>
                      <Card size="sm">
                        <CardHeader>
                          <CardTitle>
                            <h3>{term.name}</h3>
                          </CardTitle>
                          {term.aliases && term.aliases.length > 0 ? (
                            <p className="text-muted-foreground text-xs">{term.aliases.join(" · ")}</p>
                          ) : null}
                        </CardHeader>
                        <CardContent>
                          <p className="text-sm leading-relaxed">{term.description}</p>
                        </CardContent>
                      </Card>
                    </li>
                  ))}
                </ul>
              </section>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}
