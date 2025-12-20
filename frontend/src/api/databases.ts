import client from "./client";

export interface Database {
  id: number;
  name: string;
  size: number;
  tables_count?: number;
  created_at: string;
}

export interface DatabaseUser {
  id: number;
  username: string;
  host: string;
  databases: string[];
  created_at: string;
}

export interface ApiResponse<T> {
  success: boolean;
  message: string;
  data: T;
}

export const databasesApi = {
  // Databases
  list: async (): Promise<Database[]> => {
    const response = await client.get<ApiResponse<Database[]>>("/databases");
    return response.data.data;
  },

  create: async (data: { name: string }): Promise<Database> => {
    const response = await client.post<ApiResponse<Database>>(
      "/databases",
      data,
    );
    return response.data.data;
  },

  get: async (id: number): Promise<Database> => {
    const response = await client.get<ApiResponse<Database>>(
      `/databases/${id}`,
    );
    return response.data.data;
  },

  delete: async (id: number): Promise<void> => {
    await client.delete(`/databases/${id}`);
  },

  getSize: async (id: number): Promise<{ size: number }> => {
    const response = await client.get(`/databases/${id}/size`);
    return response.data.data;
  },

  grant: async (
    id: number,
    userId: number,
    privileges: string[],
  ): Promise<void> => {
    await client.post(`/databases/${id}/grant`, {
      user_id: userId,
      privileges,
    });
  },

  revoke: async (id: number, userId: number): Promise<void> => {
    await client.post(`/databases/${id}/revoke`, { user_id: userId });
  },

  // Database Users
  listUsers: async (): Promise<DatabaseUser[]> => {
    const response =
      await client.get<ApiResponse<DatabaseUser[]>>("/databases/users");
    return response.data.data;
  },

  createUser: async (data: {
    username: string;
    password: string;
    host?: string;
  }): Promise<DatabaseUser> => {
    const response = await client.post<ApiResponse<DatabaseUser>>(
      "/databases/users",
      data,
    );
    return response.data.data;
  },

  getUser: async (id: number): Promise<DatabaseUser> => {
    const response = await client.get<ApiResponse<DatabaseUser>>(
      `/databases/users/${id}`,
    );
    return response.data.data;
  },

  deleteUser: async (id: number): Promise<void> => {
    await client.delete(`/databases/users/${id}`);
  },

  changeUserPassword: async (id: number, password: string): Promise<void> => {
    await client.put(`/databases/users/${id}/password`, { password });
  },
};

export default databasesApi;
