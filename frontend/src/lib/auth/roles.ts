export const SIMA_ROLE_OPTIONS = [
  { value: "admin", label: "Administrator" },
  { value: "bendahara", label: "Bendahara" },
  { value: "verifikator", label: "Verifikator" },
  { value: "ketua", label: "Ketua" },
  { value: "auditor", label: "Auditor" },
  { value: "donatur", label: "Donatur" },
] as const;

export function roleLabel(role: string): string {
  return SIMA_ROLE_OPTIONS.find((option) => option.value === role)?.label ?? role;
}
