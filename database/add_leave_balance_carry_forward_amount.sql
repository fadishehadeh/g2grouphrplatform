ALTER TABLE leave_balances
    ADD COLUMN carry_forward_amount DECIMAL(6,2) NOT NULL DEFAULT 0.00
    AFTER adjusted_amount;
