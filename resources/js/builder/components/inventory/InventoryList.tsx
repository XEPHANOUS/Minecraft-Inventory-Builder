import * as React from "react";
import {useEffect, useRef, useState} from "react";
import InventoryHeader from './InventoryHeader'
import api from '../../services/api';
import {Form} from "react-bootstrap";
import InventoryTableLine from "./InventoryTableLine";

const InventoryList = ({folder = null}) => {

    const [inventories, setInventories] = useState(null);
    const [data, setData] = useState([]);
    const [sortConfig, setSortConfig] = useState({key: 'created_at', direction: 'ascending', search: ''});
    const wrapperRef = useRef(null);


    useEffect(() => {
        setInventories(null);
        fetchInventories();
    }, [folder]);

    useEffect(() => {
        if (inventories != null && inventories.length > 0) {
            sortData(sortConfig.key, sortConfig.search, sortConfig.direction)
        }
    }, [inventories])

    const updateInventory = (inventoryId, newData) => {
        setInventories(inventories.map(inventory =>
            inventory.id === inventoryId ? {...inventory, ...newData} : inventory
        ));
    };

    const fetchInventories = () => {

        if (!folder) return

        api.fetchInventories(folder.id).then(response => {
            if (response.data.result === 'success') {
                const inventories = response.data.inventories;
                setInventories(inventories)
            }
        }).catch(error => {
            console.log(error);
        });
    }

    const toggleSortDirection = (key) => {
        let newDirection = sortConfig.direction
        if (key === sortConfig.key) {
            newDirection = sortConfig.direction === 'ascending' ? 'descending' : 'ascending';
        }

        sortData(key, sortConfig.search, newDirection)
    };

    const sortData = (key, search = '', direction = sortConfig.direction) => {

        const filteredAndSortedData = [...inventories].filter(item => {
            return search.length === 0 || (item.file_name.toLowerCase().includes(search.toLowerCase()) || item.name.toLowerCase().includes(search.toLowerCase()));
        }).sort((a, b) => {
            if (a[key] < b[key]) {
                return direction === 'ascending' ? -1 : 1;
            }
            if (a[key] > b[key]) {
                return direction === 'ascending' ? 1 : -1;
            }
            return 0;
        });

        setData(filteredAndSortedData);
        setSortConfig({key, direction, search});
    };

    const getClassName = (key) => `sort${sortConfig.key === key ? ' sort-current' : ''}`;

    const getArrow = (key) => sortConfig.key !== key ? '' : sortConfig.direction === 'ascending' ?
        <i className="bi bi-arrow-down"/> : <i className="bi bi-arrow-up"/>

    // @ts-ignore
    const handleCreateInventory = async (formData: FormData) => {
        try {
            const response = await api.createInventory(formData, folder.id)
            api.displayToast(response)

            if (response.data.result === 'success') {
                // @ts-ignore
                setInventories(currentInventories => [
                    // @ts-ignore
                    ...currentInventories,
                    response.data.inventory
                ]);
            }
        } catch (error) {
            console.error("Error creating folder:", error);
            // @ts-ignore
            window.toast('error', 'Error !', `Error creating folder: ${error}`, 5000)
        }
    }

    // @ts-ignore
    const handleCopyInventory = async (inventoryId) => {
        try {
            const response = await api.copyInventory(inventoryId)
            api.displayToast(response)

            if (response.data.result === 'success') {
                // @ts-ignore
                setInventories(currentInventories => [
                    // @ts-ignore
                    ...currentInventories,
                    response.data.inventory
                ]);
            }
        } catch (error) {
            console.error("Error coping folder:", error);
            // @ts-ignore
            window.toast('error', 'Error !', `Error coping folder: ${error}`, 5000)
        }
    }

    const handleDeleteInventory = (inventoryId) => {
        api.deleteInventory(inventoryId).then(response => {
            api.displayToast(response)

            setInventories(currentInventories => currentInventories.filter(inventory => inventory.id !== inventoryId));
        })
    }


    return (
        <div className={'inventories'} ref={wrapperRef}>
            <InventoryHeader createInventory={handleCreateInventory}/>

            <div className={'inventory-table'}>

                <div className={'inventory-table-search'}>
                    <div className={'search-icon'}>
                        <i className="bi bi-filter"/>
                    </div>
                    <div className={'search-bar'}>
                        <Form.Control
                            type="text"
                            value={sortConfig.search}
                            placeholder={'Filter'}
                            onChange={(event) => {
                                sortData(sortConfig.key, event.target.value)
                            }}
                            className={'rounded-0'}
                        />
                    </div>
                </div>
                <hr/>
                <div className={'inventory-table-content'}>
                    <table className="table table-responsive table-striped table-hover">
                        <thead>
                        <tr>
                            <th className={'select'}></th>
                            <th className={getClassName('file_name')}
                                onClick={() => toggleSortDirection('file_name')}>File Name {getArrow('file_name')}</th>
                            <th className={getClassName('name')}
                                onClick={() => toggleSortDirection('name')}>Name {getArrow('name')}</th>
                            <th className={getClassName('size')}
                                onClick={() => toggleSortDirection('size')}>Size {getArrow('size')}</th>
                            <th className={getClassName('inventory_visibility_id')} style={{width: '150px'}}
                                onClick={() => toggleSortDirection('inventory_visibility_id')}>Visibility {getArrow('inventory_visibility_id')}</th>
                            <th className={getClassName('created_at')}
                                onClick={() => toggleSortDirection('created_at')}>Created
                                at {getArrow('created_at')}</th>
                            <th className={getClassName('updated_at')}
                                onClick={() => toggleSortDirection('updated_at')}>Updated
                                at {getArrow('updated_at')}</th>
                            <th>Action</th>
                        </tr>
                        </thead>
                        <tbody>
                        {data.map((inventory, index) => (
                            <InventoryTableLine key={`${index}-${folder.id}`} inventory={inventory}
                                                handleDeleteInventory={handleDeleteInventory}
                                                handleCopyInventory={handleCopyInventory}
                                                updateInventory={updateInventory}/>
                        ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    )
}

export default InventoryList
