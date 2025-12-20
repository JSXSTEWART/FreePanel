import client from './client'

export interface TerminalSession {
  session_id: string
  cwd: string
  created_at: string
}

export interface CommandResult {
  output: string
  exit_code: number
  cwd: string
}

export interface CommandHistory {
  id: number
  command: string
  output?: string
  exit_code?: number
  executed_at: string
}

export interface AutocompleteResult {
  suggestions: string[]
  type: 'file' | 'directory' | 'command' | 'argument'
}

interface ApiResponse<T> {
  success: boolean
  message: string
  data: T
}

export const terminalApi = {
  createSession: async (): Promise<TerminalSession> => {
    const response = await client.post<ApiResponse<TerminalSession>>('/terminal/session')
    return response.data.data
  },

  execute: async (command: string, sessionId?: string): Promise<CommandResult> => {
    const response = await client.post<ApiResponse<CommandResult>>('/terminal/execute', {
      command,
      session_id: sessionId,
    })
    return response.data.data
  },

  changeDirectory: async (path: string, sessionId?: string): Promise<{ cwd: string }> => {
    const response = await client.post<ApiResponse<{ cwd: string }>>('/terminal/cd', {
      path,
      session_id: sessionId,
    })
    return response.data.data
  },

  getHistory: async (limit?: number): Promise<CommandHistory[]> => {
    const response = await client.get<ApiResponse<CommandHistory[]>>('/terminal/history', {
      params: { limit },
    })
    return response.data.data
  },

  closeSession: async (sessionId: string): Promise<void> => {
    await client.delete('/terminal/session', {
      data: { session_id: sessionId },
    })
  },

  autocomplete: async (input: string, cwd?: string): Promise<AutocompleteResult> => {
    const response = await client.post<ApiResponse<AutocompleteResult>>('/terminal/complete', {
      input,
      cwd,
    })
    return response.data.data
  },
}

export default terminalApi
