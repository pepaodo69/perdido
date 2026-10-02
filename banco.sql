-- Criar e selecionar a base de dados
CREATE DATABASE db_sistema_integrado;
USE db_sistema_integrado;

-- ==========================================================
-- MÓDULO DE GESTÃO E E-COMMERCE (Baseado na API Node.js)
-- ==========================================================

-- Tabela de Clientes
CREATE TABLE clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    telefone VARCHAR(50),
    status VARCHAR(50) DEFAULT 'ativo'
);

-- Tabela de Produtos
CREATE TABLE produtos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    descricao TEXT,
    preco DECIMAL(10,2) NOT NULL,
    estoque INT DEFAULT 0,
    status VARCHAR(50) DEFAULT 'ativo'
);

-- Tabela de Pedidos
CREATE TABLE pedidos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NOT NULL,
    data_pedido DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pendente', 'pago', 'cancelado') DEFAULT 'pendente',
    valor_total DECIMAL(10,2) DEFAULT 0.00,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE
);

-- Tabela de Itens do Pedido (Relacionamento N:N)
CREATE TABLE itens_pedido (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id INT NOT NULL,
    produto_id INT NOT NULL,
    quantidade INT NOT NULL,
    preco_unitario DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE,
    FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE CASCADE
);

-- Tabela de Utilizadores (Acesso ao Sistema de Gestão)
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    perfil VARCHAR(50) DEFAULT 'operador',
    status VARCHAR(50) DEFAULT 'ativo',
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
);


-- ==========================================================
-- MÓDULO DE HELPDESK / SUPORTE (Baseado na Aplicação PHP)
-- ==========================================================

-- Tabela Principal de Chamados (Tickets)
CREATE TABLE chamada (
    Id_Chamada INT AUTO_INCREMENT PRIMARY KEY,
    Horario DATETIME DEFAULT CURRENT_TIMESTAMP,
    Descricao TEXT NOT NULL,
    Prioridade VARCHAR(50),
    Status VARCHAR(50) DEFAULT 'aberto'
);

-- Tabela de Utilizadores/Solicitantes do Chamado
-- Nota: Mantido no singular conforme o seu código PHP original
CREATE TABLE usuario (
    idChamada INT,
    Nome VARCHAR(255) NOT NULL,
    Email VARCHAR(255),
    Telefone VARCHAR(50),
    Tipo_Usuario VARCHAR(50),
    FOREIGN KEY (idChamada) REFERENCES chamada(Id_Chamada) ON DELETE CASCADE
);

-- Tabela de Atendentes designados aos chamados
CREATE TABLE atendente (
    Id_Atendente INT AUTO_INCREMENT PRIMARY KEY,
    idChamada INT,
    Status_de_Atendimento VARCHAR(50),
    Area_de_Atendimento VARCHAR(100),
    Turno VARCHAR(50),
    FOREIGN KEY (idChamada) REFERENCES chamada(Id_Chamada) ON DELETE SET NULL
);

-- Tabela de Histórico (Registo de atualizações do chamado)
CREATE TABLE historico (
    id_historico INT AUTO_INCREMENT PRIMARY KEY,
    idChamada INT NOT NULL,
    Data_Atualizacao DATETIME DEFAULT CURRENT_TIMESTAMP,
    Detalhes TEXT,
    FOREIGN KEY (idChamada) REFERENCES chamada(Id_Chamada) ON DELETE CASCADE
);