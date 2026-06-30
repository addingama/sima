"use client";

import { type Control, Controller, useWatch } from "react-hook-form";

import { RelationSelect } from "@/components/sima/crud/relation-select";
import { CurrencyDisplay } from "@/components/sima/currency-display";
import { MoneyInput } from "@/components/sima/money-input";
import { Checkbox } from "@/components/ui/checkbox";
import { Field, FieldContent, FieldDescription, FieldError, FieldLabel } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import { NativeSelect, NativeSelectOption } from "@/components/ui/native-select";
import { Textarea } from "@/components/ui/textarea";
import type { FormFieldDef } from "@/lib/resources/types";
import { cn } from "@/lib/utils";

export function CrudFormFields({
  fields,
  control,
  isEdit = false,
}: {
  fields: FormFieldDef[];
  control: Control<Record<string, unknown>>;
  isEdit?: boolean;
}) {
  const values = (useWatch({ control }) ?? {}) as Record<string, unknown>;

  return (
    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
      {fields.map((field) => {
        const isVisible = (!field.showOnEditOnly || isEdit) && (!field.visibleWhen || field.visibleWhen(values));

        const spanFull = field.type === "textarea";
        const isReadOnly = field.readOnly || field.autoGenerate;

        return (
          <Controller
            key={field.name}
            control={control}
            name={field.name}
            render={({ field: formField, fieldState }) => (
              <Field
                className={cn(spanFull ? "gap-1.5 md:col-span-2" : "gap-1.5", !isVisible && "hidden")}
                data-invalid={fieldState.invalid}
              >
                {field.type !== "checkbox" ? (
                  <FieldLabel htmlFor={field.name}>
                    {field.label}
                    {field.required ? <span className="text-destructive"> *</span> : null}
                  </FieldLabel>
                ) : null}
                {field.type === "textarea" ? (
                  <Textarea
                    {...formField}
                    id={field.name}
                    value={String(formField.value ?? "")}
                    placeholder={field.placeholder}
                    readOnly={isReadOnly}
                    aria-invalid={fieldState.invalid}
                  />
                ) : field.type === "select" ? (
                  <NativeSelect
                    id={field.name}
                    value={String(formField.value ?? "")}
                    onChange={(event) => formField.onChange(event.target.value)}
                    disabled={isReadOnly}
                    aria-invalid={fieldState.invalid}
                  >
                    <NativeSelectOption value="">Pilih {field.label.toLowerCase()}</NativeSelectOption>
                    {field.options?.map((option) => (
                      <NativeSelectOption key={option.value} value={option.value}>
                        {option.label}
                      </NativeSelectOption>
                    ))}
                  </NativeSelect>
                ) : field.type === "relation" && field.relation ? (
                  <RelationSelect
                    resource={field.relation.resource}
                    labelKey={field.relation.labelKey}
                    valueKey={field.relation.valueKey}
                    params={field.relation.params}
                    value={String(formField.value ?? "")}
                    onChange={formField.onChange}
                    placeholder={`Pilih ${field.label.toLowerCase()}`}
                  />
                ) : field.type === "checkbox" ? (
                  <Field orientation="horizontal">
                    <Checkbox
                      id={field.name}
                      checked={Boolean(formField.value)}
                      onCheckedChange={(checked) => formField.onChange(Boolean(checked))}
                      aria-invalid={fieldState.invalid}
                    />
                    <FieldContent>
                      <FieldLabel htmlFor={field.name} className="font-normal">
                        {field.label}
                        {field.required ? <span className="text-destructive"> *</span> : null}
                      </FieldLabel>
                    </FieldContent>
                  </Field>
                ) : field.type === "currency" && isReadOnly ? (
                  <div className="flex h-8 items-center rounded-lg border border-input bg-muted/30 px-2.5 text-sm">
                    <CurrencyDisplay value={formField.value as string | number} />
                  </div>
                ) : field.type === "currency" ? (
                  <MoneyInput
                    id={field.name}
                    value={formField.value as string | number}
                    onChange={formField.onChange}
                    onBlur={formField.onBlur}
                    placeholder={field.placeholder ?? "0"}
                    aria-invalid={fieldState.invalid}
                  />
                ) : (
                  <Input
                    {...formField}
                    id={field.name}
                    type={field.type === "number" ? "text" : field.type}
                    value={String(formField.value ?? "")}
                    placeholder={field.placeholder}
                    readOnly={isReadOnly}
                    aria-invalid={fieldState.invalid}
                  />
                )}
                {field.helperText ? <FieldDescription>{field.helperText}</FieldDescription> : null}
                {fieldState.invalid ? <FieldError errors={[fieldState.error]} /> : null}
              </Field>
            )}
          />
        );
      })}
    </div>
  );
}
