--
-- Name: holdings ; Type: TABLE; Schema: public; Owner: postgres; Tablespace: 
--
CREATE TABLE holdings (
    hid serial not null,
    pfid integer not null,
    symb character varying(10) NOT NULL,
    date date not null,
    price numeric(9,2) NOT NULL,
    volume numeric(12) NOT NULL,
    comment varchar(100)
);
CREATE INDEX idx_holdings_hid ON holdings (hid);
ALTER TABLE public.holdings OWNER TO postgres;
ALTER TABLE ONLY holdings ADD CONSTRAINT holdings_pkey PRIMARY KEY (pfid, symb, date);