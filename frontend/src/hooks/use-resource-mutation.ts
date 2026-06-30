"use client";

import { useMutation, useQueryClient } from "@tanstack/react-query";

import { apiDelete, apiPost, apiPut } from "@/lib/api/client";

function invalidateResourceQueries(
  queryClient: ReturnType<typeof useQueryClient>,
  resource: string,
  id?: string | number,
) {
  queryClient.invalidateQueries({ queryKey: [resource] });

  if (id !== undefined) {
    queryClient.invalidateQueries({ queryKey: [resource, id] });
  }
}

export function useResourceCreate<T>(resource: string) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (body: unknown) => {
      const response = await apiPost<T>(resource, body);

      return response.data;
    },
    onSuccess: () => invalidateResourceQueries(queryClient, resource),
  });
}

export function useResourceUpdate<T>(resource: string, id: string | number) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (body: unknown) => {
      const response = await apiPut<T>(`${resource}/${id}`, body);

      return response.data;
    },
    onSuccess: () => invalidateResourceQueries(queryClient, resource, id),
  });
}

export function useResourceDelete(resource: string, id?: string | number) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (targetId?: string | number) => {
      const resolvedId = targetId ?? id;

      if (resolvedId === undefined) {
        throw new Error("ID wajib untuk menghapus data.");
      }

      await apiDelete(`${resource}/${resolvedId}`);
    },
    onSuccess: (_data, targetId) => {
      invalidateResourceQueries(queryClient, resource, targetId ?? id);
    },
  });
}

export function useWorkflowAction<T>(resource: string, id: string | number) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async ({ action, body }: { action: string; body?: unknown }) => {
      const response = await apiPost<T>(`${resource}/${id}/${action}`, body);

      return response.data;
    },
    onSuccess: () => {
      invalidateResourceQueries(queryClient, resource, id);
      queryClient.invalidateQueries({ queryKey: ["/audits"] });
    },
  });
}
