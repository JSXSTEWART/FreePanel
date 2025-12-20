import { describe, it, expect, vi } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/react'
import Button from '../Button'

describe('Button', () => {
  it('renders children correctly', () => {
    render(<Button>Click me</Button>)
    expect(screen.getByRole('button', { name: /click me/i })).toBeInTheDocument()
  })

  it('applies primary variant by default', () => {
    render(<Button>Primary</Button>)
    const button = screen.getByRole('button')
    expect(button).toHaveClass('btn-primary')
  })

  it('applies different variants correctly', () => {
    const { rerender } = render(<Button variant="secondary">Secondary</Button>)
    expect(screen.getByRole('button')).toHaveClass('btn-secondary')

    rerender(<Button variant="danger">Danger</Button>)
    expect(screen.getByRole('button')).toHaveClass('btn-danger')

    rerender(<Button variant="success">Success</Button>)
    expect(screen.getByRole('button')).toHaveClass('btn-success')

    rerender(<Button variant="ghost">Ghost</Button>)
    expect(screen.getByRole('button')).toHaveClass('btn-ghost')
  })

  it('applies size classes correctly', () => {
    const { rerender } = render(<Button size="xs">XS</Button>)
    expect(screen.getByRole('button')).toHaveClass('px-2', 'py-1', 'text-xs')

    rerender(<Button size="sm">SM</Button>)
    expect(screen.getByRole('button')).toHaveClass('px-3', 'text-xs')

    rerender(<Button size="md">MD</Button>)
    expect(screen.getByRole('button')).toHaveClass('px-4', 'py-2', 'text-sm')

    rerender(<Button size="lg">LG</Button>)
    expect(screen.getByRole('button')).toHaveClass('px-6', 'py-3', 'text-base')
  })

  it('handles click events', () => {
    const handleClick = vi.fn()
    render(<Button onClick={handleClick}>Click</Button>)

    fireEvent.click(screen.getByRole('button'))
    expect(handleClick).toHaveBeenCalledTimes(1)
  })

  it('is disabled when disabled prop is true', () => {
    render(<Button disabled>Disabled</Button>)
    expect(screen.getByRole('button')).toBeDisabled()
  })

  it('shows loading state when isLoading is true', () => {
    render(<Button isLoading>Submit</Button>)
    const button = screen.getByRole('button')

    expect(button).toBeDisabled()
    expect(screen.getByText('Loading...')).toBeInTheDocument()
  })

  it('renders left icon when provided', () => {
    render(
      <Button leftIcon={<span data-testid="left-icon">←</span>}>
        With Icon
      </Button>
    )
    expect(screen.getByTestId('left-icon')).toBeInTheDocument()
  })

  it('renders right icon when provided', () => {
    render(
      <Button rightIcon={<span data-testid="right-icon">→</span>}>
        With Icon
      </Button>
    )
    expect(screen.getByTestId('right-icon')).toBeInTheDocument()
  })

  it('applies fullWidth class when prop is true', () => {
    render(<Button fullWidth>Full Width</Button>)
    expect(screen.getByRole('button')).toHaveClass('w-full')
  })

  it('applies custom className', () => {
    render(<Button className="custom-class">Custom</Button>)
    expect(screen.getByRole('button')).toHaveClass('custom-class')
  })

  it('forwards ref correctly', () => {
    const ref = vi.fn()
    render(<Button ref={ref}>Ref Button</Button>)
    expect(ref).toHaveBeenCalled()
  })
})
