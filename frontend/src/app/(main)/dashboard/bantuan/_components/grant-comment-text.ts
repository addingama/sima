export type MentionUser = { id: number; name: string };

export type CommentSegment =
  | { type: "text"; text: string; offset: number }
  | { type: "mention"; id: number; name: string; offset: number };

const MENTION_TOKEN = /@\[user:(\d+)\]/g;

export function commentCountLabel(count: number | null | undefined): string | null {
  if (!count || count < 1) {
    return null;
  }

  return count === 1 ? "1 komentar" : `${count} komentar`;
}

export function mentionQueryAtCursor(value: string, cursor: number): { start: number; query: string } | null {
  const upto = value.slice(0, cursor);
  const match = /(?:^|\s)@([^\s@]*)$/.exec(upto);

  if (!match) {
    return null;
  }

  const query = match[1] ?? "";

  return { start: upto.lastIndexOf("@"), query };
}

export function insertMention(
  value: string,
  start: number,
  cursor: number,
  user: MentionUser,
): { value: string; cursor: number } {
  const token = `@${user.name}`;
  const nextChar = value.slice(cursor, cursor + 1);
  const spacer = nextChar !== "" && /\s/.test(nextChar) ? "" : " ";
  const next = `${value.slice(0, start)}${token}${spacer}${value.slice(cursor)}`;

  return { value: next, cursor: start + token.length + spacer.length };
}

export function toStoredBody(editorText: string, mentions: MentionUser[]): string {
  const sorted = [...mentions].sort((left, right) => right.name.length - left.name.length);
  let stored = editorText;

  for (const mention of sorted) {
    stored = stored.split(`@${mention.name}`).join(`@[user:${mention.id}]`);
  }

  return stored;
}

export function toEditorText(body: string, mentions: MentionUser[]): string {
  return body.replace(MENTION_TOKEN, (_token, id: string) => {
    const mention = mentions.find((item) => item.id === Number(id));

    return mention ? `@${mention.name}` : "@pengguna";
  });
}

export function commentSegments(body: string, mentions: MentionUser[]): CommentSegment[] {
  const segments: CommentSegment[] = [];
  const pattern = new RegExp(MENTION_TOKEN.source, "g");
  let last = 0;

  for (const match of body.matchAll(pattern)) {
    const index = match.index ?? 0;

    if (index > last) {
      segments.push({ type: "text", text: body.slice(last, index), offset: last });
    }

    const id = Number(match[1]);
    const mention = mentions.find((item) => item.id === id);
    segments.push({ type: "mention", id, name: mention?.name ?? "pengguna", offset: index });
    last = index + match[0].length;
  }

  if (last < body.length) {
    segments.push({ type: "text", text: body.slice(last), offset: last });
  }

  return segments;
}
