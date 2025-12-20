import client from "./client";

export interface Backup {
  id: number;
  filename: string;
  size: number;
  type: "full" | "partial";
  includes: {
    files: boolean;
    databases: boolean;
    emails: boolean;
  };
  status: "pending" | "in_progress" | "completed" | "failed";
  error_message?: string;
  created_at: string;
  completed_at?: string;
}

export interface ApiResponse<T> {
  success: boolean;
  message: string;
  data: T;
}

export const backupsApi = {
  list: async (): Promise<Backup[]> => {
    const response = await client.get<ApiResponse<Backup[]>>("/backups");
    return response.data.data;
  },

  get: async (id: number): Promise<Backup> => {
    const response = await client.get<ApiResponse<Backup>>(`/backups/${id}`);
    return response.data.data;
  },

  create: async (options?: {
    include_files?: boolean;
    include_databases?: boolean;
    include_emails?: boolean;
  }): Promise<Backup> => {
    const response = await client.post<ApiResponse<Backup>>(
      "/backups",
      options,
    );
    return response.data.data;
  },

  delete: async (id: number): Promise<void> => {
    await client.delete(`/backups/${id}`);
  },

  download: async (id: number): Promise<Blob> => {
    const response = await client.get(`/backups/${id}/download`, {
      responseType: "blob",
    });
    return response.data;
  },

  restore: async (
    id: number,
    options?: {
      restore_files?: boolean;
      restore_databases?: boolean;
      restore_emails?: boolean;
    },
  ): Promise<void> => {
    await client.post(`/backups/${id}/restore`, options);
  },
};

export default backupsApi;
