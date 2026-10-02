// routes/pedidos.js
const express = require('express');
const router = express.Router();
const db = require('../db');

// POST /pedidos: Criar novo pedido vinculado a um cliente_id
router.post('/', async (req, res) => {
    const { cliente_id } = req.body;
    if (!cliente_id) {
        return res.status(400).json({ mensagem: 'O campo cliente_id é obrigatório.' });
    }

    try {
        // Verifica se o cliente existe
        const [clienteRows] = await db.execute('SELECT id FROM clientes WHERE id = ?', [cliente_id]);
        if (clienteRows.length === 0) {
            return res.status(404).json({ mensagem: 'Cliente não encontrado.' });
        }

        const [result] = await db.execute(
            'INSERT INTO pedidos (cliente_id, status, valor_total) VALUES (?, ?, ?)',
            [cliente_id, 'pendente', 0.00]
        );

        res.status(201).json({
            id: result.insertId,
            cliente_id,
            status: 'pendente',
            valor_total: 0.00
        });
    } catch (error) {
        res.status(500).json({ mensagem: 'Erro ao criar pedido.', detalhes: error.message });
    }
});

// GET /pedidos: Listar todos os pedidos (com dados básicos do cliente)
router.get('/', async (req, res) => {
    try {
        const query = `
            SELECT p.id as pedido_id, p.data_pedido, p.status, p.valor_total,
                   c.id as cliente_id, c.nome as cliente_nome, c.email as cliente_email
            FROM pedidos p
            JOIN clientes c ON p.cliente_id = c.id
        `;
        const [rows] = await db.execute(query);
        res.status(200).json(rows);
    } catch (error) {
        res.status(500).json({ mensagem: 'Erro ao listar pedidos.', detalhes: error.message });
    }
});

// GET /pedidos/:id: Buscar pedido completo (pedido + cliente + itens)
router.get('/:id', async (req, res) => {
    const { id } = req.params;
    try {
        const queryPedido = `
            SELECT p.id as pedido_id, p.data_pedido, p.status, p.valor_total,
                   c.id as cliente_id, c.nome as cliente_nome, c.email as cliente_email, c.telefone as cliente_telefone
            FROM pedidos p
            JOIN clientes c ON p.cliente_id = c.id
            WHERE p.id = ?
        `;
        const [pedidoRows] = await db.execute(queryPedido, [id]);
        if (pedidoRows.length === 0) {
            return res.status(404).json({ mensagem: 'Pedido não encontrado.' });
        }

        const queryItens = `
            SELECT i.id as item_id, i.quantidade, i.preco_unitario,
                   pr.id as produto_id, pr.nome as produto_nome, pr.descricao as produto_descricao
            FROM itens_pedido i
            JOIN produtos pr ON i.produto_id = pr.id
            WHERE i.pedido_id = ?
        `;
        const [itensRows] = await db.execute(queryItens, [id]);

        const pedidoCompleto = {
            ...pedidoRows[0],
            itens: itensRows
        };

        res.status(200).json(pedidoCompleto);
    } catch (error) {
        res.status(500).json({ mensagem: 'Erro ao buscar pedido.', detalhes: error.message });
    }
});

// PATCH /pedidos/:id/status: Alterar status do pedido
router.patch('/:id/status', async (req, res) => {
    const { id } = req.params;
    const { status } = req.body;

    if (!['pendente', 'pago', 'cancelado'].includes(status)) {
        return res.status(400).json({ mensagem: 'Status inválido. Use: pendente, pago ou cancelado.' });
    }

    try {
        const [result] = await db.execute('UPDATE pedidos SET status = ? WHERE id = ?', [status, id]);
        if (result.affectedRows === 0) {
            return res.status(404).json({ mensagem: 'Pedido não encontrado.' });
        }
        res.status(200).json({ mensagem: 'Status do pedido atualizado com sucesso.' });
    } catch (error) {
        res.status(500).json({ mensagem: 'Erro ao atualizar status do pedido.', detalhes: error.message });
    }
});

// POST /pedidos/:id/itens: Adicionar novo item a um pedido existente
router.post('/:id/itens', async (req, res) => {
    const { id: pedido_id } = req.params;
    const { produto_id, quantidade, preco_unitario } = req.body;

    if (!produto_id || !quantidade || preco_unitario === undefined) {
        return res.status(400).json({ mensagem: 'produto_id, quantidade e preco_unitario são obrigatórios.' });
    }

    try {
        // Verifica se o pedido existe
        const [pedidoRows] = await db.execute('SELECT id FROM pedidos WHERE id = ?', [pedido_id]);
        if (pedidoRows.length === 0) {
            return res.status(404).json({ mensagem: 'Pedido não encontrado.' });
        }

        // Verifica se o produto existe
        const [produtoRows] = await db.execute('SELECT id FROM produtos WHERE id = ?', [produto_id]);
        if (produtoRows.length === 0) {
            return res.status(404).json({ mensagem: 'Produto não encontrado.' });
        }

        // Insere o item no pedido
        const [resultItem] = await db.execute(
            'INSERT INTO itens_pedido (pedido_id, produto_id, quantidade, preco_unitario) VALUES (?, ?, ?, ?)',
            [pedido_id, produto_id, quantidade, preco_unitario]
        );

        // Atualiza o valor_total do pedido
        const [somaRows] = await db.execute(
            'SELECT SUM(quantidade * preco_unitario) as total FROM itens_pedido WHERE pedido_id = ?',
            [pedido_id]
        );
        const novoTotal = somaRows[0].total || 0;

        await db.execute('UPDATE pedidos SET valor_total = ? WHERE id = ?', [novoTotal, pedido_id]);

        res.status(201).json({
            mensagem: 'Item adicionado com sucesso ao pedido.',
            item_id: resultItem.insertId,
            pedido_id,
            produto_id,
            quantidade,
            preco_unitario,
            novo_valor_total: novoTotal
        });
    } catch (error) {
        res.status(500).json({ mensagem: 'Erro ao adicionar item ao pedido.', detalhes: error.message });
    }
});

// DELETE /pedidos/:id_pedido/itens/:id_item: Remover item específico de um pedido
router.delete('/:id_pedido/itens/:id_item', async (req, res) => {
    const { id_pedido, id_item } = req.params;

    try {
        const [result] = await db.execute('DELETE FROM itens_pedido WHERE id = ? AND pedido_id = ?', [id_item, id_pedido]);
        if (result.affectedRows === 0) {
            return res.status(404).json({ mensagem: 'Item ou pedido não encontrado.' });
        }

        // Recalcula o valor_total do pedido
        const [somaRows] = await db.execute(
            'SELECT SUM(quantidade * preco_unitario) as total FROM itens_pedido WHERE pedido_id = ?',
            [id_pedido]
        );
        const novoTotal = somaRows[0].total || 0;

        await db.execute('UPDATE pedidos SET valor_total = ? WHERE id = ?', [novoTotal, id_pedido]);

        res.status(200).json({
            mensagem: 'Item removido com sucesso.',
            novo_valor_total: novoTotal
        });
    } catch (error) {
        res.status(500).json({ mensagem: 'Erro ao remover item do pedido.', detalhes: error.message });
    }
});

module.exports = router;